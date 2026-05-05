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

// ── Aggregation query: own row, public + protected lists, 4 time windows ─────
// LEFT JOIN cells restricted to this player; LEFT JOIN lists restricted to public/protected.
// WHERE (cells.id IS NULL OR lists.id IS NOT NULL) ensures CROSS JOIN rows are kept but
// cells belonging to private lists are excluded (lists.id IS NULL means visibility filter rejected them).
$player_stats = [];

if (!empty($global_columns)) {
    $agg_sql = "
        SELECT
            c.id        AS column_id,
            c.name      AS column_name,
            c.data_type,

            -- Gesamt: all public/protected cells (dated and undated)
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CASE WHEN cells.id IS NOT NULL THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN cells.id IS NOT NULL AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
                END, 0
            ) AS sum_all,

            -- Letzte 4 Wochen: lists.date within last 28 days
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '28 days' AND lists.date <= CURRENT_DATE THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '28 days' AND lists.date <= CURRENT_DATE AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
                END, 0
            ) AS sum_4w,

            -- 4–8 Wochen
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '56 days' AND lists.date < CURRENT_DATE - INTERVAL '28 days' THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '56 days' AND lists.date < CURRENT_DATE - INTERVAL '28 days' AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
                END, 0
            ) AS sum_4_8w,

            -- 8–12 Wochen
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '84 days' AND lists.date < CURRENT_DATE - INTERVAL '56 days' THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '84 days' AND lists.date < CURRENT_DATE - INTERVAL '56 days' AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
                END, 0
            ) AS sum_8_12w

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
        $player_stats[(int)$row['column_id']] = [
            'all'    => (float)$row['sum_all'],
            '4w'     => (float)$row['sum_4w'],
            '4_8w'   => (float)$row['sum_4_8w'],
            '8_12w'  => (float)$row['sum_8_12w'],
        ];
    }
}

// ── Per-list breakdown: lists with global columns for this member ─────────────
// Uses list_global_columns join table to find which lists have global columns attached.
// Member sees public + protected lists only (D-05).
$per_list_rows   = [];
$per_list_cells  = [];
$per_list_totals = [];
$col_list_counts = [];

if (!empty($global_columns)) {
    // Query A: lists that have at least one global column attached (via list_global_columns)
    // and are visible to member (public + protected).
    $lists_stmt = $pdo->prepare("
        SELECT DISTINCT l.id, l.name, l.date
        FROM lists l
        JOIN list_global_columns lgc ON lgc.list_id = l.id
        JOIN columns c ON c.id = lgc.column_id
            AND c.team_id = :team_id AND c.list_id IS NULL AND c.is_active = TRUE
        WHERE l.team_id = :team_id2
          AND l.visibility IN ('public', 'protected')
        ORDER BY l.date DESC NULLS LAST, l.name
    ");
    $lists_stmt->execute([':team_id' => $team_id, ':team_id2' => $team_id]);
    $per_list_rows = $lists_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query B: cells for this member across those lists
    $cells_stmt = $pdo->prepare("
        SELECT ce.list_id, ce.column_id, ce.value
        FROM cells ce
        JOIN lists l ON l.id = ce.list_id
            AND l.team_id = :team_id AND l.visibility IN ('public', 'protected')
        JOIN columns c ON c.id = ce.column_id
            AND c.team_id = :team_id2 AND c.list_id IS NULL AND c.is_active = TRUE
        WHERE ce.player_id = :player_id
    ");
    $cells_stmt->execute([':team_id' => $team_id, ':team_id2' => $team_id, ':player_id' => $player_id]);
    foreach ($cells_stmt->fetchAll(PDO::FETCH_ASSOC) as $cell) {
        $per_list_cells[(int)$cell['list_id']][(int)$cell['column_id']] = $cell['value'];
    }

    // Query C: total lists per column (denominator for boolean %)
    $cnt_stmt = $pdo->prepare("
        SELECT c.id AS column_id, COUNT(DISTINCT l.id) AS total_lists
        FROM columns c
        JOIN list_global_columns lgc ON lgc.column_id = c.id
        JOIN lists l ON l.id = lgc.list_id
            AND l.team_id = :team_id AND l.visibility IN ('public', 'protected')
        WHERE c.team_id = :team_id2 AND c.list_id IS NULL AND c.is_active = TRUE
        GROUP BY c.id
    ");
    $cnt_stmt->execute([':team_id' => $team_id, ':team_id2' => $team_id]);
    foreach ($cnt_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $col_list_counts[(int)$row['column_id']] = (int)$row['total_lists'];
    }

    // Compute totals per column
    foreach ($global_columns as $col) {
        $cid = (int)$col['id'];
        if ($col['data_type'] === 'number') {
            $sum = 0.0;
            foreach ($per_list_cells as $list_cells) {
                if (isset($list_cells[$cid])) {
                    $sum += (float)$list_cells[$cid];
                }
            }
            $per_list_totals[$cid] = ['sum' => $sum];
        } else {
            // boolean
            $count_true = 0;
            foreach ($per_list_cells as $list_cells) {
                if (isset($list_cells[$cid]) && in_array($list_cells[$cid], ['1', 'true'], true)) {
                    $count_true++;
                }
            }
            $per_list_totals[$cid] = ['count_true' => $count_true];
        }
    }
}

require ROOT_PATH . '/src/templates/member/layout.php';

render_player_page('Meine Statistik', 'stats', function() use (
    $global_columns, $player_stats,
    $per_list_rows, $per_list_cells, $per_list_totals, $col_list_counts
) {
    require ROOT_PATH . '/src/templates/member/stats.php';
});
