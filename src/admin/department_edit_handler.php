<?php
// src/admin/department_edit_handler.php — GET: edit form; POST: save name and symbol (DEPARTMENT_ICONS)
// $_REQUEST['department_id'] set by router

declare(strict_types=1);

require_admin();

$department_id = (int)($_REQUEST['department_id'] ?? 0);
$pdo  = get_db();
require_once ROOT_PATH . '/src/db/departments.php';
$stmt = $pdo->prepare("SELECT id, name, icon FROM departments WHERE id = ?");
$stmt->execute([$department_id]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$department) redirect('/admin/departments');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) {
        redirect('/admin/departments/' . $department_id . '/edit?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }
    $icon = (string)($_POST['icon'] ?? '');
    $icon = isset(DEPARTMENT_ICONS[$icon]) ? $icon : null;   // nur aus der Auswahl, sonst kein Symbol
    $pdo->prepare("UPDATE departments SET name = ?, icon = ? WHERE id = ?")->execute([$name, $icon, $department_id]);
    redirect('/admin/departments?success=' . urlencode($name . ' gespeichert.'));
}

$error = (string)($_GET['error'] ?? '');

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Abteilung bearbeiten', 'settings', function() use ($department, $error) {
    require ROOT_PATH . '/src/templates/admin/department_edit.php';
});
