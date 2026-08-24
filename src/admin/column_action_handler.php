<?php
// src/admin/column_action_handler.php — GET (confirm) / POST (delete) /admin/columns/{id}/delete
// If cells exist: converts system column into per-team coordinator global columns (one per team).
// If no cells: deletes directly.

declare(strict_types=1);

require_admin();

$column_id = isset($matches[1]) ? (int)$matches[1] : 0;

if ($column_id <= 0) {
    redirect('/admin/columns');
}

$pdo = get_db();

$col_stmt = $pdo->prepare(
    "SELECT id, name, data_type, sort_order FROM columns
     WHERE id = ? AND is_system = TRUE AND list_id IS NULL"
);
$col_stmt->execute([$column_id]);
$column = $col_stmt->fetch(PDO::FETCH_ASSOC);

if (!$column) {
    redirect('/admin/columns');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Find distinct teams that have this column in any of their lists
    $teams_stmt = $pdo->prepare(
        "SELECT DISTINCT l.team_id
         FROM list_global_columns lgc
         JOIN lists l ON l.id = lgc.list_id
         WHERE lgc.column_id = ?"
    );
    $teams_stmt->execute([$column_id]);
    $team_ids = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($team_ids)) {
        try {
            $pdo->beginTransaction();

            foreach ($team_ids as $team_id) {
                $team_id = (int)$team_id;

                // Create coordinator global column for this team
                $ins = $pdo->prepare(
                    "INSERT INTO columns (team_id, list_id, name, data_type, is_system, sort_order)
                     VALUES (?, NULL, ?, ?, FALSE, ?) RETURNING id"
                );
                $ins->execute([$team_id, $column['name'], $column['data_type'], (int)$column['sort_order']]);
                $new_col_id = (int)$ins->fetchColumn();

                // Remap list_global_columns for this team's lists
                $pdo->prepare(
                    "UPDATE list_global_columns SET column_id = ?
                     WHERE column_id = ?
                       AND list_id IN (SELECT id FROM lists WHERE team_id = ?)
                       AND NOT EXISTS (
                           SELECT 1 FROM list_global_columns ex
                           WHERE ex.list_id = list_global_columns.list_id AND ex.column_id = ?
                       )"
                )->execute([$new_col_id, $column_id, $team_id, $new_col_id]);

                // Delete any remaining lgc rows for old column in this team's lists (conflict duplicates)
                $pdo->prepare(
                    "DELETE FROM list_global_columns
                     WHERE column_id = ? AND list_id IN (SELECT id FROM lists WHERE team_id = ?)"
                )->execute([$column_id, $team_id]);

                // Remap cells for this team's lists
                $pdo->prepare(
                    "UPDATE cells SET column_id = ?
                     WHERE column_id = ?
                       AND list_id IN (SELECT id FROM lists WHERE team_id = ?)
                       AND NOT EXISTS (
                           SELECT 1 FROM cells ex
                           WHERE ex.list_id = cells.list_id
                             AND ex.column_id = ?
                             AND ex.member_id = cells.member_id
                       )"
                )->execute([$new_col_id, $column_id, $team_id, $new_col_id]);

                // Delete any remaining cells (conflict duplicates) for this team's lists
                $pdo->prepare(
                    "DELETE FROM cells
                     WHERE column_id = ? AND list_id IN (SELECT id FROM lists WHERE team_id = ?)"
                )->execute([$column_id, $team_id]);
            }

            // Cleanup any orphaned lgc/cells not covered above (unlikely but safe)
            $pdo->prepare("DELETE FROM list_global_columns WHERE column_id = ?")->execute([$column_id]);
            $pdo->prepare("DELETE FROM cells WHERE column_id = ?")->execute([$column_id]);

            $pdo->prepare("DELETE FROM columns WHERE id = ? AND is_system = TRUE")->execute([$column_id]);

            $pdo->commit();
            redirect('/admin/columns?converted=' . count($team_ids));
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Column convert-delete error: ' . $e->getMessage());
            redirect('/admin/columns?error=' . urlencode('Ein Fehler ist aufgetreten. Bitte versuche es erneut.'));
        }
    } else {
        // No lists use this column — safe direct delete
        $pdo->prepare("DELETE FROM cells WHERE column_id = ?")->execute([$column_id]);
        $pdo->prepare("DELETE FROM columns WHERE id = ? AND is_system = TRUE")->execute([$column_id]);
        redirect('/admin/columns?deleted=1');
    }
}

// GET: load cell and team counts for the confirmation page
$cell_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM cells WHERE column_id = ?");
$cell_count_stmt->execute([$column_id]);
$cell_count = (int)$cell_count_stmt->fetchColumn();

$team_count_stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT l.team_id)
     FROM list_global_columns lgc
     JOIN lists l ON l.id = lgc.list_id
     WHERE lgc.column_id = ?"
);
$team_count_stmt->execute([$column_id]);
$team_count = (int)$team_count_stmt->fetchColumn();

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Systemspalte löschen', 'settings', function() use ($column, $cell_count, $team_count) {
    require ROOT_PATH . '/src/templates/admin/column_delete_confirm.php';
});
