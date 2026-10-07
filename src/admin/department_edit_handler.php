<?php
// src/admin/department_edit_handler.php — GET: rename form; POST: save name
// $_REQUEST['department_id'] set by router

declare(strict_types=1);

require_admin();

$department_id = (int)($_REQUEST['department_id'] ?? 0);
$pdo  = get_db();
$stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
$stmt->execute([$department_id]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$department) redirect('/admin/departments');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) {
        redirect('/admin/departments/' . $department_id . '/edit?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }
    $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?")->execute([$name, $department_id]);
    redirect('/admin/departments?success=' . urlencode($name . ' gespeichert.'));
}

$error = (string)($_GET['error'] ?? '');

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Abteilung umbenennen', 'settings', function() use ($department, $error) {
    require ROOT_PATH . '/src/templates/admin/department_edit.php';
});
