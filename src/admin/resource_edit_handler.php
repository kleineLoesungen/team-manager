<?php
// src/admin/resource_edit_handler.php — GET: rename form; POST: save name
// $_REQUEST['resource_id'] set by router

declare(strict_types=1);

require_admin();

$resource_id = (int)($_REQUEST['resource_id'] ?? 0);
$pdo  = get_db();
$stmt = $pdo->prepare("SELECT id, name FROM resources WHERE id = ?");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$resource) redirect('/admin/resources');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) {
        redirect('/admin/resources/' . $resource_id . '/edit?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }
    $pdo->prepare("UPDATE resources SET name = ? WHERE id = ?")->execute([$name, $resource_id]);
    redirect('/admin/resources?success=' . urlencode($name . ' gespeichert.'));
}

$error = (string)($_GET['error'] ?? '');

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Ressource bearbeiten', 'settings', function() use ($resource, $error) {
    require ROOT_PATH . '/src/templates/admin/resource_edit.php';
});
