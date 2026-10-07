<?php
// src/admin/resources_handler.php — GET: list resources; POST: add one
// Ressourcen (Platz, Halle, Bus …) gelten für alle Teams; Koordinatoren belegen sie bei Listen
// und Terminen (src/db/resources.php).

declare(strict_types=1);

require_admin();
require_once ROOT_PATH . '/src/db/departments.php';
$pdo = get_db();
$departments = departments_list($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name          = trim($_POST['name'] ?? '');
    $department_id = (int)($_POST['department_id'] ?? 0);
    if ($name === '' || mb_strlen($name) > 100) {
        redirect('/admin/resources?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }
    if (!in_array($department_id, array_map('intval', array_column(departments_list($pdo, true), 'id')), true)) {
        redirect('/admin/resources?error=' . urlencode('Wähle die Abteilung der Ressource.'));
    }
    $pdo->prepare("INSERT INTO resources (name, department_id) VALUES (?, ?)")->execute([$name, $department_id]);
    redirect('/admin/resources?success=' . urlencode($name . ' hinzugefügt.'));
}

$department = department_filter($departments);
$stmt = $pdo->prepare(
    "SELECT r.id, r.name, r.is_active, d.name AS department_name,
            (SELECT COUNT(*) FROM resource_bookings b WHERE b.resource_id = r.id) AS bookings
     FROM resources r JOIN departments d ON d.id = r.department_id
     WHERE (CAST(? AS int) IS NULL OR r.department_id = ?)
     ORDER BY r.is_active DESC, r.name"
);
$stmt->execute([$department, $department]);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active   = array_values(array_filter($resources, fn($r) => in_array($r['is_active'], [true, 1, '1', 't'], true)));
$inactive = array_values(array_filter($resources, fn($r) => !in_array($r['is_active'], [true, 1, '1', 't'], true)));
$error    = (string)($_GET['error'] ?? '');
$success  = (string)($_GET['success'] ?? '');

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Ressourcen', 'settings', function() use ($active, $inactive, $error, $success, $departments, $department) {
    require ROOT_PATH . '/src/templates/admin/resources.php';
});
