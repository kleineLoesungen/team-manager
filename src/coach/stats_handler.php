<?php
// src/coach/stats_handler.php — GET /coach/stats — statistics + leaderboard (STAT-01, STAT-02, STAT-03)
// Per D-02: coaches see ALL list visibility states (public, protected, private).

declare(strict_types=1);

require_coach();

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];

// ── Filter parameters (STAT-02) ───────────────────────────────────────────────
$filter_list_id   = isset($_GET['list_id'])   && $_GET['list_id']   !== '' ? (int)$_GET['list_id']   : null;
$filter_date_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from']       : null;
$filter_date_to   = isset($_GET['date_to'])   && $_GET['date_to']   !== '' ? $_GET['date_to']         : null;

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

if ($filter_date_from !== null) {
    $agg_sql    .= " AND (cells.updated_at >= ? OR cells.updated_at IS NULL)";
    $agg_params[] = $filter_date_from . ' 00:00:00+00';
}

if ($filter_date_to !== null) {
    $agg_sql    .= " AND (cells.updated_at <= ? OR cells.updated_at IS NULL)";
    $agg_params[] = $filter_date_to . ' 23:59:59+00';
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

// ── Leaderboard (STAT-03) ────────────────────────────────────────────────────
// GET param: sort_by = column_id. Default: first global column (if any).
$sort_by_id = isset($_GET['sort_by']) && $_GET['sort_by'] !== '' ? (int)$_GET['sort_by'] : 0;

// Default to first global column if no sort_by set
if ($sort_by_id === 0 && !empty($global_columns)) {
    $sort_by_id = (int)$global_columns[0]['id'];
}

$leaderboard        = [];
$leaderboard_column = null;

if ($sort_by_id > 0 && !empty($global_columns)) {
    // Find the column metadata for the chosen sort column
    foreach ($global_columns as $col) {
        if ((int)$col['id'] === $sort_by_id) {
            $leaderboard_column = $col;
            break;
        }
    }
}

if ($leaderboard_column !== null) {
    $lb_type = $leaderboard_column['data_type'];
    $lb_sql  = "
        SELECT
            u.id AS player_id,
            u.first_name,
            u.last_name,
            COALESCE(
                CASE
                    WHEN ? = 'number'  THEN SUM(CAST(cells.value AS NUMERIC))
                    WHEN ? = 'boolean' THEN SUM(CASE WHEN cells.value = 'true' OR cells.value = '1' THEN 1 ELSE 0 END)
                END,
                0
            ) AS rank_value
        FROM users u
        LEFT JOIN cells ON cells.player_id = u.id AND cells.column_id = ?
        LEFT JOIN lists ON cells.list_id = lists.id
        WHERE u.team_id = ?
          AND u.role = 'player'
          AND u.is_active = TRUE
    ";
    $lb_params = [$lb_type, $lb_type, $sort_by_id, $team_id];

    if ($filter_list_id !== null) {
        $lb_sql    .= " AND (cells.list_id = ? OR cells.list_id IS NULL)";
        $lb_params[] = $filter_list_id;
    }
    if ($filter_date_from !== null) {
        $lb_sql    .= " AND (cells.updated_at >= ? OR cells.updated_at IS NULL)";
        $lb_params[] = $filter_date_from . ' 00:00:00+00';
    }
    if ($filter_date_to !== null) {
        $lb_sql    .= " AND (cells.updated_at <= ? OR cells.updated_at IS NULL)";
        $lb_params[] = $filter_date_to . ' 23:59:59+00';
    }

    $lb_sql .= " GROUP BY u.id, u.first_name, u.last_name ORDER BY rank_value DESC NULLS LAST, u.last_name, u.first_name";

    $lb_stmt = $pdo->prepare($lb_sql);
    $lb_stmt->execute($lb_params);
    $leaderboard = $lb_stmt->fetchAll(PDO::FETCH_ASSOC);
}

require ROOT_PATH . '/src/templates/coach/layout.php';

render_coach_page('Statistik', 'stats', function() use (
    $global_columns, $player_stats, $player_order,
    $available_lists, $filter_list_id, $filter_date_from, $filter_date_to,
    $leaderboard, $leaderboard_column, $sort_by_id
) {
    require ROOT_PATH . '/src/templates/coach/stats.php';
});
