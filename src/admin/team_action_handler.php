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

} elseif ($action === 'reactivate') {
    $stmt = $pdo->prepare("UPDATE teams SET is_active = TRUE WHERE id = ?");
    $stmt->execute([$team_id]);
    redirect('/admin/teams');

} elseif ($action === 'delete') {
    // Only delete users who belong EXCLUSIVELY to this team.
    // Multi-team coordinators (with other active coordinator_teams rows) are preserved.
    // coordinator_teams rows for this team cascade-delete with the team (ON DELETE CASCADE).
    $pdo->prepare(
        "DELETE FROM users WHERE team_id = ?
         AND NOT EXISTS (
             SELECT 1 FROM coordinator_teams
             WHERE coordinator_teams.user_id = users.id
               AND coordinator_teams.team_id != ?
               AND coordinator_teams.left_at IS NULL
         )"
    )->execute([$team_id, $team_id]);
    $pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$team_id]);
    redirect('/admin/teams');

} else {
    redirect('/admin/teams');
}
