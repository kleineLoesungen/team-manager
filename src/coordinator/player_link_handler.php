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

// Allow caller to request redirect back to the list instead of the profile
$_back_raw = $_POST['_back'] ?? '';
$back = (str_starts_with($_back_raw, '/coordinator/players') && !str_contains($_back_raw, '//'))
    ? $_back_raw
    : '/coordinator/players/' . $player_id;

// Use admin context to verify the player exists — coordinators can manage links for any player,
// including ones not yet linked to their team.
set_admin_context($pdo);
$check = $pdo->prepare("SELECT id FROM players WHERE id = ?");
$check->execute([$player_id]);
if (!$check->fetch()) redirect('/coordinator/players');
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

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

    // Enforce one-to-one: player may have at most one user per team
    $dup = $pdo->prepare(
        "SELECT id FROM users WHERE player_id = ? AND team_id = ? AND role = 'member'"
    );
    $dup->execute([$player_id, $team_id]);
    if ($dup->fetch()) {
        redirect($back . '?error=' . urlencode('Dieser Spieler ist in deinem Team bereits mit einem Account verknüpft.'));
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
