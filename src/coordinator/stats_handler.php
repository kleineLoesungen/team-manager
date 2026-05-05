<?php
// src/coach/stats_handler.php — GET /coach/stats — statistics + ranking (STAT-01, STAT-02, STAT-03)
// Per D-02: coaches see ALL list visibility states (public, protected, private).

declare(strict_types=1);

require_coordinator();

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
      AND u.role = 'member'
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
    ORDER BY u.first_name, u.last_name, c.sort_order
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

// col_filter: always a specific column — default to first column when unset or invalid
$col_filter = isset($_GET['col_filter']) && $_GET['col_filter'] !== '' ? (int)$_GET['col_filter'] : 0;
if (!in_array($col_filter, $valid_col_ids, true)) {
    $col_filter = !empty($valid_col_ids) ? $valid_col_ids[0] : 0;
}

// Build the ranking query with 4 time-window conditional SUMs
// "Gesamt" (sum_all) respects the existing date filters; time-window columns use fixed intervals.
// list_id filter applies to all columns.

// Build the sum_all date condition mirroring the $date_conds approach above, but as CASE WHEN
// so it works inside SUM without WHERE-level filtering.
// The date condition for sum_all must be expressed inline in CASE WHEN.
// We'll build the condition as a SQL fragment for the CASE WHEN ... THEN ... END.
// For simplicity: if date filters apply, we only SUM when the cell matches date criteria.
$sum_all_date_inner   = '1=1'; // default: include all cells
$ranking_extra_params = [];

if ($filter_date_from !== null || $filter_date_to !== null) {
    $dc = [];
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
    if (!empty($dc)) {
        $sum_all_date_inner = '(' . implode(' OR ', $dc) . ')';
    }
}

// Param order: sum_all date params + count_all date params + CROSS JOIN team_id + WHERE team_id
$ranking_params = array_merge(
    $ranking_extra_params, // date params for sum_all
    $ranking_extra_params, // date params for count_all
    [$team_id, $team_id]   // CROSS JOIN subquery + main WHERE
);

$ranking_sql = "
    SELECT
        u.id           AS player_id,
        u.first_name,
        u.last_name,
        c.id           AS column_id,
        c.name         AS column_name,
        c.data_type,
        c.sort_order,

        COALESCE(SUM(
            CASE
                WHEN cells.id IS NULL THEN NULL
                WHEN {$sum_all_date_inner} THEN
                    CASE
                        WHEN c.data_type = 'number' AND cells.value IS NOT NULL THEN CAST(cells.value AS NUMERIC)
                        WHEN c.data_type = 'boolean' AND cells.value IN ('true','1') THEN 1.0
                        ELSE 0.0
                    END
                ELSE NULL
            END
        ), 0) AS sum_all,

        COALESCE(SUM(
            CASE WHEN cells.id IS NOT NULL AND {$sum_all_date_inner} THEN 1 ELSE NULL END
        ), 0) AS count_all,

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
        ) AS sum_8_12w,

        COALESCE(SUM(CASE WHEN cells.id IS NOT NULL AND lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '28 days' AND lists.date <= CURRENT_DATE THEN 1 ELSE NULL END), 0) AS count_4w,

        COALESCE(SUM(CASE WHEN cells.id IS NOT NULL AND lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '56 days' AND lists.date < CURRENT_DATE - INTERVAL '28 days' THEN 1 ELSE NULL END), 0) AS count_4_8w,

        COALESCE(SUM(CASE WHEN cells.id IS NOT NULL AND lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '84 days' AND lists.date < CURRENT_DATE - INTERVAL '56 days' THEN 1 ELSE NULL END), 0) AS count_8_12w

    FROM users u
    CROSS JOIN (
        SELECT id, name, data_type, sort_order
        FROM columns
        WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
    ) c
    LEFT JOIN cells ON cells.player_id = u.id AND cells.column_id = c.id
    LEFT JOIN lists ON cells.list_id = lists.id
    WHERE u.team_id = ?
      AND u.role = 'member'
      AND u.is_active = TRUE
";

if ($filter_list_id !== null) {
    $ranking_sql    .= " AND (cells.list_id = ? OR cells.list_id IS NULL)";
    $ranking_params[] = $filter_list_id;
}

$ranking_sql .= "
    GROUP BY u.id, u.first_name, u.last_name, c.id, c.name, c.data_type, c.sort_order
    ORDER BY u.first_name, u.last_name, c.sort_order
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
        'all'        => (float)$row['sum_all'],
        '4w'         => (float)$row['sum_4w'],
        '4_8w'       => (float)$row['sum_4_8w'],
        '8_12w'      => (float)$row['sum_8_12w'],
        'cnt_all'    => (int)$row['count_all'],
        'cnt_4w'     => (int)$row['count_4w'],
        'cnt_4_8w'   => (int)$row['count_4_8w'],
        'cnt_8_12w'  => (int)$row['count_8_12w'],
    ];
}

// Column totals per window (used for number % display)
$col_totals = [];
foreach ($ranking as $pdata) {
    foreach ($pdata['cols'] as $cid => $wins) {
        foreach (['all', '4w', '4_8w', '8_12w'] as $w) {
            $col_totals[$cid][$w] = ($col_totals[$cid][$w] ?? 0.0) + $wins[$w];
        }
    }
}

// Sort $ranking_order by chosen sort_col + sort_win descending; ties broken by first_name, last_name
if ($sort_col_id > 0) {
    usort($ranking_order, function(int $a, int $b) use ($ranking, $sort_col_id, $sort_win): int {
        $va = $ranking[$a]['cols'][$sort_col_id][$sort_win] ?? 0;
        $vb = $ranking[$b]['cols'][$sort_col_id][$sort_win] ?? 0;
        if ($va !== $vb) {
            return $vb <=> $va; // descending
        }
        $cmp = strcmp($ranking[$a]['first_name'], $ranking[$b]['first_name']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($ranking[$a]['last_name'], $ranking[$b]['last_name']);
    });
}

// ── Per-list breakdown for selected member (coordinator view) ──────────────────
// Fetch all active members for the selector dropdown
$members_stmt = $pdo->prepare(
    "SELECT id, first_name, last_name FROM users
     WHERE team_id = ? AND role = 'member' AND is_active = TRUE
     ORDER BY first_name, last_name"
);
$members_stmt->execute([$team_id]);
$all_members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

// Parse + validate selected member from GET
$selected_member_id = isset($_GET['member_id']) && $_GET['member_id'] !== ''
    ? (int)$_GET['member_id']
    : null;
$valid_member_ids = array_map('intval', array_column($all_members, 'id'));
if ($selected_member_id !== null && !in_array($selected_member_id, $valid_member_ids, true)) {
    $selected_member_id = null;
}

$mod_per_list_rows   = [];
$mod_per_list_cells  = [];
$mod_per_list_totals = [];
$mod_col_list_counts = [];
$selected_member_name = null;

if ($selected_member_id !== null) {
    // Find selected member name for heading
    foreach ($all_members as $m) {
        if ((int)$m['id'] === $selected_member_id) {
            $selected_member_name = $m['first_name'] . ' ' . $m['last_name'];
            break;
        }
    }

    // Query A: lists with global columns for selected member (no visibility filter — coordinator sees all)
    $mod_lists_stmt = $pdo->prepare("
        SELECT DISTINCT l.id, l.name, l.date
        FROM lists l
        JOIN list_global_columns lgc ON lgc.list_id = l.id
        JOIN columns c ON c.id = lgc.column_id
            AND c.team_id = :team_id AND c.list_id IS NULL AND c.is_active = TRUE
        WHERE l.team_id = :team_id2
        ORDER BY l.date DESC NULLS LAST, l.name
    ");
    $mod_lists_stmt->execute([':team_id' => $team_id, ':team_id2' => $team_id]);
    $mod_per_list_rows = $mod_lists_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query B: cells for selected member (no visibility filter)
    $mod_cells_stmt = $pdo->prepare("
        SELECT ce.list_id, ce.column_id, ce.value
        FROM cells ce
        JOIN lists l ON l.id = ce.list_id AND l.team_id = :team_id
        JOIN columns c ON c.id = ce.column_id
            AND c.team_id = :team_id2 AND c.list_id IS NULL AND c.is_active = TRUE
        WHERE ce.player_id = :member_id
    ");
    $mod_cells_stmt->execute([':team_id' => $team_id, ':team_id2' => $team_id, ':member_id' => $selected_member_id]);
    foreach ($mod_cells_stmt->fetchAll(PDO::FETCH_ASSOC) as $cell) {
        $mod_per_list_cells[(int)$cell['list_id']][(int)$cell['column_id']] = $cell['value'];
    }

    // Query C: total lists per column (no visibility filter — coordinator sees all)
    $mod_cnt_stmt = $pdo->prepare("
        SELECT c.id AS column_id, COUNT(DISTINCT l.id) AS total_lists
        FROM columns c
        JOIN list_global_columns lgc ON lgc.column_id = c.id
        JOIN lists l ON l.id = lgc.list_id AND l.team_id = :team_id
        WHERE c.team_id = :team_id2 AND c.list_id IS NULL AND c.is_active = TRUE
        GROUP BY c.id
    ");
    $mod_cnt_stmt->execute([':team_id' => $team_id, ':team_id2' => $team_id]);
    foreach ($mod_cnt_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mod_col_list_counts[(int)$row['column_id']] = (int)$row['total_lists'];
    }

    // Compute totals per column
    foreach ($global_columns as $col) {
        $cid = (int)$col['id'];
        if ($col['data_type'] === 'number') {
            $sum = 0.0;
            foreach ($mod_per_list_cells as $list_cells) {
                if (isset($list_cells[$cid])) {
                    $sum += (float)$list_cells[$cid];
                }
            }
            $mod_per_list_totals[$cid] = ['sum' => $sum];
        } else {
            $count_true = 0;
            foreach ($mod_per_list_cells as $list_cells) {
                if (isset($list_cells[$cid]) && in_array($list_cells[$cid], ['1', 'true'], true)) {
                    $count_true++;
                }
            }
            $mod_per_list_totals[$cid] = ['count_true' => $count_true];
        }
    }
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Statistik', 'stats', function() use (
    $global_columns, $player_stats, $player_order,
    $available_lists, $filter_list_id, $filter_date_from, $filter_date_to, $filter_include_undated,
    $ranking, $ranking_order, $sort_col_id, $sort_win, $col_filter, $col_totals,
    $all_members, $selected_member_id, $selected_member_name,
    $mod_per_list_rows, $mod_per_list_cells, $mod_per_list_totals, $mod_col_list_counts
) {
    require ROOT_PATH . '/src/templates/coordinator/stats.php';
});
