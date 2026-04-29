<?php
// src/admin/team_action_handler.php — POST: edit or deactivate a team (TEAM-03)
// $_REQUEST['team_id'] and $_REQUEST['action'] set by router

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/teams');
}

require_csrf();

$team_id = (int)($_REQUEST['team_id'] ?? 0);
$action  = $_REQUEST['action'] ?? '';

if ($team_id <= 0) {
    redirect('/admin/teams');
}

$pdo = get_db();

// Verify team exists
$check = $pdo->prepare("SELECT id, name, is_active FROM teams WHERE id = ?");
$check->execute([$team_id]);
$team = $check->fetch();

if (!$team) {
    redirect('/admin/teams');
}

if ($action === 'edit') {
    $new_name = trim($_POST['team_name'] ?? '');
    if (empty($new_name) || strlen($new_name) > 100) {
        redirect('/admin/teams?error=' . urlencode('Teamname ist erforderlich (max. 100 Zeichen).'));
    }
    $stmt = $pdo->prepare("UPDATE teams SET name = ? WHERE id = ?");
    $stmt->execute([$new_name, $team_id]);
    redirect('/admin/teams');

} elseif ($action === 'deactivate') {
    $stmt = $pdo->prepare("UPDATE teams SET is_active = FALSE WHERE id = ?");
    $stmt->execute([$team_id]);
    redirect('/admin/teams');

} else {
    redirect('/admin/teams');
}
