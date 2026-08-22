<?php
// src/admin/column_action_handler.php — GET (confirm) / POST (delete) /admin/columns/{id}/delete
// Two-step deletion for system columns. Blocked if any cells reference the column.

declare(strict_types=1);

require_admin();

$column_id = isset($matches[1]) ? (int)$matches[1] : 0;

if ($column_id <= 0) {
    redirect('/admin/columns');
}

$pdo = get_db();

// Fetch the system column to confirm it exists and is a system column
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

    // Check if any cells reference this column
    $cell_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM cells WHERE column_id = ?");
    $cell_count_stmt->execute([$column_id]);
    $cell_count = (int)$cell_count_stmt->fetchColumn();

    if ($cell_count > 0) {
        redirect('/admin/columns?error=' . urlencode('Spalte kann nicht gelöscht werden, da noch Zellen vorhanden sind.'));
    }

    $del_stmt = $pdo->prepare("DELETE FROM columns WHERE id = ? AND is_system = TRUE");
    $del_stmt->execute([$column_id]);
    redirect('/admin/columns?deleted=1');
}

// GET: show confirmation page
require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Systemspalte löschen', 'settings', function() use ($column) {
    require ROOT_PATH . '/src/templates/admin/column_delete_confirm.php';
});
