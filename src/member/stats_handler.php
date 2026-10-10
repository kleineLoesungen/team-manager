<?php
// src/member/stats_handler.php — GET /member/stats — own statistics for member (STAT-01)
// Per D-04: shows only own row. Per D-05: public and protected lists only (private excluded).

declare(strict_types=1);

require_member();

$pdo       = get_db();
$team_id   = (int)$_SESSION['team_id'];
$member_id = (int)$_SESSION['user_id'];
// System columns (team_id = NULL) require admin context to bypass RLS
set_admin_context($pdo);

// ── Global columns + own totals (shared with the overview, src/db/member_stats.php) ──
require_once ROOT_PATH . '/src/db/member_stats.php';
$global_columns = member_stats_global_columns($pdo, $team_id);
$member_stats   = !empty($global_columns) ? member_stats_totals($pdo, $team_id, $member_id) : [];

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
            AND (c.team_id = :team_id OR c.is_system = TRUE) AND c.list_id IS NULL AND c.is_active = TRUE
        WHERE l.team_id = :team_id2
          AND l.visibility IN ('public', 'protected')
          AND l.date IS NOT NULL AND l.date <= CURRENT_DATE
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
            AND l.date IS NOT NULL AND l.date <= CURRENT_DATE
        JOIN columns c ON c.id = ce.column_id
            AND (c.team_id = :team_id2 OR c.is_system = TRUE) AND c.list_id IS NULL AND c.is_active = TRUE
        WHERE ce.member_id = :member_id
          -- Column must still be attached to THIS list. Unlinking a global column can
          -- leave its cells behind, and without this they keep counting.
          AND EXISTS (
              SELECT 1 FROM list_global_columns lgc
              WHERE lgc.list_id = ce.list_id AND lgc.column_id = ce.column_id
          )
    ");
    $cells_stmt->execute([':team_id' => $team_id, ':team_id2' => $team_id, ':member_id' => $member_id]);
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
            AND l.date IS NOT NULL AND l.date <= CURRENT_DATE
        WHERE (c.team_id = :team_id2 OR c.is_system = TRUE) AND c.list_id IS NULL AND c.is_active = TRUE
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

render_member_page('Meine Statistik', 'stats', function() use (
    $global_columns, $member_stats,
    $per_list_rows, $per_list_cells, $per_list_totals, $col_list_counts
) {
    require ROOT_PATH . '/src/templates/member/stats.php';
});
