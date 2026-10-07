<?php
// src/admin/organization_action_handler.php — POST: edit, deactivate, reactivate a organization
// $_REQUEST['organization_id'] and $_REQUEST['action'] set by router

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/organizations');
}

require_csrf();

$organization_id = (int)($_REQUEST['organization_id'] ?? 0);
$action  = $_REQUEST['action'] ?? '';

if ($organization_id <= 0) {
    redirect('/admin/organizations');
}

$pdo   = get_db();
$check = $pdo->prepare("SELECT id, name, is_active FROM organizations WHERE id = ?");
$check->execute([$organization_id]);
$organization = $check->fetch();

if (!$organization) {
    redirect('/admin/organizations');
}

if ($action === 'edit') {
    $new_name = trim($_POST['name'] ?? '');
    if (empty($new_name) || mb_strlen($new_name) > 100) {
        redirect('/admin/organizations?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }
    $pdo->prepare("UPDATE organizations SET name = ? WHERE id = ?")->execute([$new_name, $organization_id]);
    redirect('/admin/organizations');

} elseif ($action === 'deactivate') {
    $pdo->prepare("UPDATE organizations SET is_active = FALSE WHERE id = ?")->execute([$organization_id]);
    redirect('/admin/organizations');

} elseif ($action === 'reactivate') {
    $pdo->prepare("UPDATE organizations SET is_active = TRUE WHERE id = ?")->execute([$organization_id]);
    redirect('/admin/organizations');

} else {
    redirect('/admin/organizations');
}
