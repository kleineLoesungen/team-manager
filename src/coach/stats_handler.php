<?php
// src/coach/stats_handler.php — GET /coach/stats — statistics + ranking (STAT-01, STAT-02, STAT-03)
// Per D-02: coaches see ALL list visibility states (public, protected, private).

declare(strict_types=1);

require_coach();

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];

// ── Filter parameters (STAT-02) ───────────────────────────────────────────────
$filter_list_id          = isset($_GET['list_id'])   && $_GET['list_id']   !== '' ? (int)$_GET['list_id']   : null;
$filter_date_from        = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from']       : null;
$filter_date_to          = isset($_GET['date_to'])   && $_GET['date_to']   !== '' ? $_GET['date_to']         : null;
$filter_include_undated  = !empty($_GET['include_undated']);

// ── Fetch available lists for filter dropdown ─────────────────────────────────
$lists_stmt = $pdo->prepare(
    "SELECT id, name, visibility FROM lists WHERE team_id = ? ORDER BY created_at DESC"
);
$lists_stmt->execute([$team_id]);
$available_lists = $lists_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch global columns for this team ───────────────────────────────────────
$cols_stmt = $pdo->prepare(
    "SELECT id, name, data_type FROM columns
     WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
     ORDER BY sort_order, id"
);
$cols_stmt->execute([$team_id]);
$global_columns = $cols_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Aggregation query (STAT-01 + STAT-02) ────────────────────────────────────
// Uses CROSS JOIN to ensure every player appears for every global column even with no cell data.
// COALESCE ensures 0 is shown instead of NULL for players with no entries.
// Coach sees ALL list types — no visibility filter (per D-02).
$agg_sql = "
    SELECT
        u.id           AS player_id,
        u.first_name,
        u.last_name,
        c.id           AS column_id,
        c.name         AS column_name,
        c.data_type,
        c.sort_order,
        COALESCE(
            CASE
                WHEN c.data_type = 'number'  THEN SUM(CAST(cells.value AS NUMERIC))
                WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN cells.value = 'true' OR cells.value = '1' THEN 1 ELSE 0 END)
                ELSE NULL
            END,
            0
        ) AS aggregated_value
    FROM users u
    CROSS JOIN (
        SELECT id, name, data_type, sort_order
        FROM columns
        WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
    ) c
    LEFT JOIN cells ON cells.player_id = u.id AND cells.column_id = c.id
    LEFT JOIN lists ON cells.list_id = lists.id
    WHERE u.team_id = ?
      AND u.role = 'player'
      AND u.is_active = TRUE
";

$agg_params = [$team_id, $team_id];

if ($filter_list_id !== null) {
    $agg_sql    .= " AND (cells.list_id = ? OR cells.list_id IS NULL)";
    $agg_params[] = $filter_list_id;
}

if ($filter_date_from !== null || $filter_date_to !== null) {
    $date_conds = ['cells.id IS NULL'];  // CROSS JOIN artifact — always include
    if ($filter_include_undated) {
        $date_conds[] = 'lists.date IS NULL';
    }
    $range_conds = [];
    if ($filter_date_from !== null) {
        $range_conds[] = 'lists.date >= ?';
        $agg_params[] = $filter_date_from;
    }
    if ($filter_date_to !== null) {
        $range_conds[] = 'lists.date <= ?';
        $agg_params[] = $filter_date_to;
    }
    if (!empty($range_conds)) {
        $date_conds[] = '(lists.date IS NOT NULL AND ' . implode(' AND ', $range_conds) . ')';
    }
    $agg_sql .= ' AND (' . implode(' OR ', $date_conds) . ')';
}

$agg_sql .= "
    GROUP BY u.id, u.first_name, u.last_name, c.id, c.name, c.data_type, c.sort_order
    ORDER BY u.last_name, u.first_name, c.sort_order
";

$agg_stmt = $pdo->prepare($agg_sql);
$agg_stmt->execute($agg_params);
$raw_stats = $agg_stmt->fetchAll(PDO::FETCH_ASSOC);

// Reshape: $player_stats[player_id] = ['first_name'=>..., 'last_name'=>..., 'cols'=>[column_id => value]]
$player_stats = [];
$player_order = [];  // Preserve sort order from query
foreach ($raw_stats as $row) {
    $pid = (int)$row['player_id'];
    if (!isset($player_stats[$pid])) {
        $player_stats[$pid] = [
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'cols'       => [],
        ];
        $player_order[] = $pid;
    }
    $player_stats[$pid]['cols'][(int)$row['column_id']] = $row['aggregated_value'];
}

// ── Ranking with time-window aggregation (STAT-03) ───────────────────────────
// GET params: sort_col (column_id), sort_win (all|4w|4_8w|8_12w)
// time-window columns use their own date ranges — they ignore date filters but respect list_id filter.

$allowed_wins = ['all', '4w', '4_8w', '8_12w'];

// Validate sort_win
$sort_win = isset($_GET['sort_win']) && in_array($_GET['sort_win'], $allowed_wins, true)
    ? $_GET['sort_win']
    : 'all';

// Validate sort_col_id (must be a known global column)
$valid_col_ids = array_map(fn($c) => (int)$c['id'], $global_columns);
$sort_col_id   = isset($_GET['sort_col']) && $_GET['sort_col'] !== '' ? (int)$_GET['sort_col'] : 0;
if (!in_array($sort_col_id, $valid_col_ids, true)) {
    $sort_col_id = !empty($valid_col_ids) ? $valid_col_ids[0] : 0;
}

// Validate col_filter (0 = alle Spalten, sonst column_id einer globalen Spalte)
$col_filter = isset($_GET['col_filter']) && $_GET['col_filter'] !== '' ? (int)$_GET['col_filter'] : 0;
if ($col_filter !== 0 && !in_array($col_filter, $valid_col_ids, true)) {
    $col_filter = 0;
}

// Build the ranking query with 4 time-window conditional SUMs
// "Gesamt" (sum_all) respects the existing date filters; time-window columns use fixed intervals.
// list_id filter applies to all columns.

// Build the sum_all date condition mirroring the $date_conds approach above, but as CASE WHEN
// so it works inside SUM without WHERE-level filtering.
$sum_all_cond_num  = 'cells.id IS NULL OR TRUE'; // default: include all rows
$sum_all_cond_bool = 'cells.id IS NULL OR TRUE';
$ranking_params    = [$team_id, $team_id];

// The date condition for sum_all must be expressed inline in CASE WHEN.
// We'll build the condition as a SQL fragment for the CASE WHEN ... THEN ... END.
// For simplicity: if date filters apply, we only SUM when the cell matches date criteria.
// The cell must match: (cells.id IS NULL) OR (date filter passes).
$sum_all_date_sql = '1=1'; // default: no filter applied, all cells count
$ranking_extra_params = [];

if ($filter_date_from !== null || $filter_date_to !== null) {
    $dc = ['cells.id IS NULL'];
    if ($filter_include_undated) {
        $dc[] = 'lists.date IS NULL';
    }
    $rc = [];
    if ($filter_date_from !== null) {
        $rc[] = 'lists.date >= ?';
        $ranking_extra_params[] = $filter_date_from;
    }
    if ($filter_date_to !== null) {
        $rc[] = 'lists.date <= ?';
        $ranking_extra_params[] = $filter_date_to;
    }
    if (!empty($rc)) {
        $dc[] = '(lists.date IS NOT NULL AND ' . implode(' AND ', $rc) . ')';
    }
    $sum_all_date_sql = '(' . implode(' OR ', $dc) . ')';
}

$ranking_sql = "
    SELECT
        u.id           AS player_id,
        u.first_name,
        u.last_name,
        c.id           AS column_id,
        c.name         AS column_name,
        c.data_type,
        c.sort_order,

        COALESCE(
            CASE
                WHEN c.data_type = 'number'  THEN SUM(CASE WHEN {$sum_all_date_sql} THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN {$sum_all_date_sql} AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
            END, 0
        ) AS sum_all,

        COALESCE(
            CASE
                WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '28 days' AND lists.date <= CURRENT_DATE THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '28 days' AND lists.date <= CURRENT_DATE AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
            END, 0
        ) AS sum_4w,

        COALESCE(
            CASE
                WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '56 days' AND lists.date < CURRENT_DATE - INTERVAL '28 days' THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '56 days' AND lists.date < CURRENT_DATE - INTERVAL '28 days' AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
            END, 0
        ) AS sum_4_8w,

        COALESCE(
            CASE
                WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '84 days' AND lists.date < CURRENT_DATE - INTERVAL '56 days' THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '84 days' AND lists.date < CURRENT_DATE - INTERVAL '56 days' AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
            END, 0
        ) AS sum_8_12w

    FROM users u
    CROSS JOIN (
        SELECT id, name, data_type, sort_order
        FROM columns
        WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
    ) c
    LEFT JOIN cells ON cells.player_id = u.id AND cells.column_id = c.id
    LEFT JOIN lists ON cells.list_id = lists.id
    WHERE u.team_id = ?
      AND u.role = 'player'
      AND u.is_active = TRUE
";

// Merge date filter params after the two team_id params
$ranking_params = array_merge($ranking_params, $ranking_extra_params);

if ($filter_list_id !== null) {
    $ranking_sql    .= " AND (cells.list_id = ? OR cells.list_id IS NULL)";
    $ranking_params[] = $filter_list_id;
}

$ranking_sql .= "
    GROUP BY u.id, u.first_name, u.last_name, c.id, c.name, c.data_type, c.sort_order
    ORDER BY u.last_name, u.first_name, c.sort_order
";

$ranking_stmt = $pdo->prepare($ranking_sql);
$ranking_stmt->execute($ranking_params);
$raw_ranking  = $ranking_stmt->fetchAll(PDO::FETCH_ASSOC);

// Reshape: $ranking[player_id] = ['first_name'=>..., 'last_name'=>..., 'cols'=>[col_id => [all,4w,4_8w,8_12w]]]
$ranking       = [];
$ranking_order = [];

foreach ($raw_ranking as $row) {
    $pid = (int)$row['player_id'];
    $cid = (int)$row['column_id'];
    if (!isset($ranking[$pid])) {
        $ranking[$pid] = [
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'cols'       => [],
        ];
        $ranking_order[] = $pid;
    }
    $ranking[$pid]['cols'][$cid] = [
        'all'    => (float)$row['sum_all'],
        '4w'     => (float)$row['sum_4w'],
        '4_8w'   => (float)$row['sum_4_8w'],
        '8_12w'  => (float)$row['sum_8_12w'],
    ];
}

// Sort $ranking_order by chosen sort_col + sort_win descending; ties broken by last_name, first_name
if ($sort_col_id > 0) {
    usort($ranking_order, function(int $a, int $b) use ($ranking, $sort_col_id, $sort_win): int {
        $va = $ranking[$a]['cols'][$sort_col_id][$sort_win] ?? 0;
        $vb = $ranking[$b]['cols'][$sort_col_id][$sort_win] ?? 0;
        if ($va !== $vb) {
            return $vb <=> $va; // descending
        }
        $cmp = strcmp($ranking[$a]['last_name'], $ranking[$b]['last_name']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($ranking[$a]['first_name'], $ranking[$b]['first_name']);
    });
}

require ROOT_PATH . '/src/templates/coach/layout.php';

render_coach_page('Statistik', 'stats', function() use (
    $global_columns, $player_stats, $player_order,
    $available_lists, $filter_list_id, $filter_date_from, $filter_date_to, $filter_include_undated,
    $ranking, $ranking_order, $sort_col_id, $sort_win, $col_filter
) {
    require ROOT_PATH . '/src/templates/coach/stats.php';
});
