<?php
// src/admin/organization_edit_handler.php — GET: show edit form; POST: update organization
// $_REQUEST['organization_id'] set by router

declare(strict_types=1);

require_admin();

$organization_id = (int)($_REQUEST['organization_id'] ?? 0);
if ($organization_id <= 0) {
    redirect('/admin/organizations');
}

$pdo  = get_db();
$stmt = $pdo->prepare("SELECT id, name FROM organizations WHERE id = ?");
$stmt->execute([$organization_id]);
$organization = $stmt->fetch();

if (!$organization) {
    redirect('/admin/organizations');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/organizations/' . $organization_id . '/edit?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }

    $pdo->prepare("UPDATE organizations SET name = ? WHERE id = ?")
        ->execute([$name, $organization_id]);

    redirect('/admin/organizations?success=' . urlencode($name . ' gespeichert.'));
}

// GET: render edit form
$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Organisation bearbeiten', 'organizations', function() use ($organization, $error) {
    require ROOT_PATH . '/src/templates/admin/organization_edit.php';
});
