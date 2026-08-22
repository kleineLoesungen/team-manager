<?php
// src/admin/column_rename_handler.php — GET/POST /admin/columns/{id}/rename
// GET: rename form. POST: rename (simple) or redirect to merge confirm if new name conflicts.

declare(strict_types=1);

require_admin();

$column_id = isset($matches[1]) ? (int)$matches[1] : 0;
if ($column_id <= 0) redirect('/admin/columns');

$pdo = get_db();

$col_stmt = $pdo->prepare(
    "SELECT id, name, data_type FROM columns WHERE id = ? AND is_system = TRUE AND list_id IS NULL"
);
$col_stmt->execute([$column_id]);
$column = $col_stmt->fetch(PDO::FETCH_ASSOC);

if (!$column) redirect('/admin/columns');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $new_name = trim($_POST['name'] ?? '');

    if ($new_name === '' || mb_strlen($new_name, 'UTF-8') > 100) {
        redirect('/admin/columns/' . $column_id . '/rename?error=' . urlencode('Name ist erforderlich (max. 100 Zeichen).'));
    }

    if ($new_name === $column['name']) {
        redirect('/admin/columns');
    }

    // Check for a system column with the same name + same type
    $conflict_stmt = $pdo->prepare(
        "SELECT id FROM columns
         WHERE name = ? AND data_type = ? AND is_system = TRUE AND list_id IS NULL AND id != ?"
    );
    $conflict_stmt->execute([$new_name, $column['data_type'], $column_id]);
    $conflict = $conflict_stmt->fetch(PDO::FETCH_ASSOC);

    if ($conflict) {
        // Same name + same type → merge confirm
        redirect('/admin/columns/' . $column_id . '/merge/' . (int)$conflict['id']);
    }

    // Check for same name but different type → block
    $wrong_type_stmt = $pdo->prepare(
        "SELECT id FROM columns
         WHERE name = ? AND is_system = TRUE AND list_id IS NULL AND id != ?"
    );
    $wrong_type_stmt->execute([$new_name, $column_id]);
    if ($wrong_type_stmt->fetch()) {
        redirect('/admin/columns/' . $column_id . '/rename?error=' . urlencode(
            'Eine Systemspalte mit diesem Namen und einem anderen Typ existiert bereits. Typen können nicht zusammengeführt werden.'
        ));
    }

    // Simple rename
    $pdo->prepare("UPDATE columns SET name = ? WHERE id = ? AND is_system = TRUE")
        ->execute([$new_name, $column_id]);

    redirect('/admin/columns?renamed=1');
}

// GET
$error = !empty($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Spalte umbenennen', 'settings', function() use ($column, $error) {
    require ROOT_PATH . '/src/templates/admin/column_rename.php';
});
