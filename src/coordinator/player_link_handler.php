<?php
// src/coordinator/player_link_handler.php — POST: link/unlink a member to a player (my team only)

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/coordinator/players');
}

require_csrf();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
$action    = $_REQUEST['action'] ?? '';

if ($player_id <= 0) redirect('/coordinator/players');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$back    = '/coordinator/players/' . $player_id;

// Verify player is accessible via this team
$check = $pdo->prepare(
    "SELECT p.id FROM players p
     WHERE p.id = ? AND EXISTS (
         SELECT 1 FROM users u WHERE u.player_id = p.id AND u.team_id = ? AND u.role = 'member'
     )"
);
$check->execute([$player_id, $team_id]);
if (!$check->fetch()) redirect('/coordinator/players');

if ($action === 'link-user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) redirect($back);

    // Must be an unlinked active member of MY team
    $u = $pdo->prepare(
        "SELECT id FROM users
         WHERE id = ? AND team_id = ? AND role = 'member' AND player_id IS NULL AND is_active = TRUE"
    );
    $u->execute([$user_id, $team_id]);
    if (!$u->fetch()) {
        redirect($back . '?error=' . urlencode('Mitglied nicht gefunden oder bereits verknüpft.'));
    }

    $pdo->prepare("UPDATE users SET player_id = ? WHERE id = ? AND team_id = ? AND role = 'member'")
        ->execute([$player_id, $user_id, $team_id]);

    redirect($back);

} elseif ($action === 'unlink-user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) redirect($back);

    // Only unlink users from MY team that are linked to THIS player
    $pdo->prepare(
        "UPDATE users SET player_id = NULL
         WHERE id = ? AND player_id = ? AND team_id = ? AND role = 'member'"
    )->execute([$user_id, $player_id, $team_id]);

    redirect($back);

} else {
    redirect($back);
}
