<?php
// src/admin/club_action_handler.php — POST: edit, deactivate, reactivate a club
// $_REQUEST['club_id'] and $_REQUEST['action'] set by router

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/clubs');
}

require_csrf();

$club_id = (int)($_REQUEST['club_id'] ?? 0);
$action  = $_REQUEST['action'] ?? '';

if ($club_id <= 0) {
    redirect('/admin/clubs');
}

$pdo   = get_db();
$check = $pdo->prepare("SELECT id, name, is_active FROM clubs WHERE id = ?");
$check->execute([$club_id]);
$club = $check->fetch();

if (!$club) {
    redirect('/admin/clubs');
}

if ($action === 'edit') {
    $new_name = trim($_POST['name'] ?? '');
    if (empty($new_name) || mb_strlen($new_name) > 100) {
        redirect('/admin/clubs?error=' . urlencode('Klubname ist erforderlich (max. 100 Zeichen).'));
    }
    $pdo->prepare("UPDATE clubs SET name = ? WHERE id = ?")->execute([$new_name, $club_id]);
    redirect('/admin/clubs');

} elseif ($action === 'deactivate') {
    $pdo->prepare("UPDATE clubs SET is_active = FALSE WHERE id = ?")->execute([$club_id]);
    redirect('/admin/clubs');

} elseif ($action === 'reactivate') {
    $pdo->prepare("UPDATE clubs SET is_active = TRUE WHERE id = ?")->execute([$club_id]);
    redirect('/admin/clubs');

} else {
    redirect('/admin/clubs');
}
