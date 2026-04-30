<?php
// src/player/stats_handler.php — GET /player/stats — own statistics for player (STAT-01)
// Per D-04: shows only own row. Per D-05: public and protected lists only (private excluded).

declare(strict_types=1);

require_player();

$pdo       = get_db();
$team_id   = (int)$_SESSION['team_id'];
$player_id = (int)$_SESSION['user_id'];

// ── Fetch global columns for this team ───────────────────────────────────────
$cols_stmt = $pdo->prepare(
    "SELECT id, name, data_type FROM columns
     WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
     ORDER BY sort_order, id"
);
$cols_stmt->execute([$team_id]);
$global_columns = $cols_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Aggregation query: own row, public + protected lists only (STAT-01, D-05) ──
// LEFT JOIN on cells filtered to current player_id only (D-04: own row).
// LEFT JOIN on lists with visibility IN ('public', 'protected') — private list cells
// produce NULL for lists.id, which the WHERE clause excludes via (cells.id IS NULL OR lists.id IS NOT NULL).
// COALESCE to 0 prevents NULL display when player has no entries.
$player_stats = [];

if (!empty($global_columns)) {
    $agg_sql = "
        SELECT
            c.id           AS column_id,
            c.name         AS column_name,
            c.data_type,
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CAST(cells.value AS NUMERIC))
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN cells.value = 'true' OR cells.value = '1' THEN 1 ELSE 0 END)
                    ELSE NULL
                END,
                0
            ) AS aggregated_value
        FROM (
            SELECT id, name, data_type, sort_order
            FROM columns
            WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
        ) c
        LEFT JOIN cells ON cells.column_id = c.id
                       AND cells.player_id = ?
        LEFT JOIN lists ON cells.list_id = lists.id
                       AND lists.visibility IN ('public', 'protected')
        WHERE (cells.id IS NULL OR lists.id IS NOT NULL)
        GROUP BY c.id, c.name, c.data_type, c.sort_order
        ORDER BY c.sort_order, c.id
    ";

    $agg_stmt = $pdo->prepare($agg_sql);
    $agg_stmt->execute([$team_id, $player_id]);
    $raw = $agg_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw as $row) {
        $player_stats[(int)$row['column_id']] = $row['aggregated_value'];
    }
}

require ROOT_PATH . '/src/templates/player/layout.php';

render_player_page('Meine Statistik', 'stats', function() use ($global_columns, $player_stats) {
    require ROOT_PATH . '/src/templates/player/stats.php';
});
