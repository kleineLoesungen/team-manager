<?php
// src/admin/resource_edit_handler.php — GET: edit form; POST: save name, department, „Für Gäste sichtbar“
// $_REQUEST['resource_id'] set by router

declare(strict_types=1);

require_admin();
require_once ROOT_PATH . '/src/db/departments.php';

$resource_id = (int)($_REQUEST['resource_id'] ?? 0);
$pdo  = get_db();
$stmt = $pdo->prepare("SELECT id, name, department_id, guest_visible FROM resources WHERE id = ?");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$resource) redirect('/admin/resources');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name          = trim($_POST['name'] ?? '');
    $department_id = (int)($_POST['department_id'] ?? 0);
    $valid_departments   = array_map('intval', array_column(departments_list($pdo, true), 'id'));
    $valid_departments[] = (int)$resource['department_id'];
    if ($name === '' || mb_strlen($name) > 100) {
        redirect('/admin/resources/' . $resource_id . '/edit?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }
    if (!in_array($department_id, $valid_departments, true)) {
        redirect('/admin/resources/' . $resource_id . '/edit?error=' . urlencode('Wähle die Abteilung der Ressource.'));
    }
    $guest = !empty($_POST['guest_visible']) ? 'true' : 'false';   // Gastbereich (Issue #15)
    $pdo->prepare("UPDATE resources SET name = ?, department_id = ?, guest_visible = ? WHERE id = ?")->execute([$name, $department_id, $guest, $resource_id]);
    redirect('/admin/resources?success=' . urlencode($name . ' gespeichert.'));
}

$error = (string)($_GET['error'] ?? '');

require ROOT_PATH . '/src/templates/admin/layout.php';

$departments = departments_list($pdo);

render_admin_page('Ressource bearbeiten', 'settings', function() use ($resource, $error, $departments) {
    require ROOT_PATH . '/src/templates/admin/resource_edit.php';
});
