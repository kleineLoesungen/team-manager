<?php
// src/admin/departments_handler.php — GET: list departments; POST: add one
// Abteilungen gruppieren Teams und Ressourcen (src/db/departments.php).

declare(strict_types=1);

require_admin();
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) {
        redirect('/admin/departments?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }
    $pdo->prepare("INSERT INTO departments (name) VALUES (?)")->execute([$name]);
    redirect('/admin/departments?success=' . urlencode($name . ' hinzugefügt.'));
}

$departments = $pdo->query(
    "SELECT d.id, d.name, d.icon, d.is_active,
            (SELECT COUNT(*) FROM teams t WHERE t.department_id = d.id AND t.is_active = TRUE) AS teams,
            (SELECT COUNT(*) FROM resources r WHERE r.department_id = d.id AND r.is_active = TRUE) AS resources
     FROM departments d ORDER BY d.is_active DESC, d.name"
)->fetchAll(PDO::FETCH_ASSOC);

$is_on    = fn($d) => in_array($d['is_active'], [true, 1, '1', 't'], true);
$active   = array_values(array_filter($departments, $is_on));
$inactive = array_values(array_filter($departments, fn($d) => !$is_on($d)));
$error    = (string)($_GET['error'] ?? '');
$success  = (string)($_GET['success'] ?? '');

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Abteilungen', 'settings', function() use ($active, $inactive, $error, $success) {
    require ROOT_PATH . '/src/templates/admin/departments.php';
});
