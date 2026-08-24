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

// Use admin context to verify the member profile exists — coordinators can manage links for any profile,
// including ones not yet linked to their team.
set_admin_context($pdo);
$check = $pdo->prepare("SELECT id FROM members WHERE id = ?");
$check->execute([$player_id]);
if (!$check->fetch()) redirect('/coordinator/players');
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

if ($action === 'link-user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) redirect($back);

    // Member must be active on MY team; after migration 024 all members already have a player link
    $u = $pdo->prepare(
        "SELECT id FROM users WHERE id = ? AND team_id = ? AND role = 'member' AND is_active = TRUE"
    );
    $u->execute([$user_id, $team_id]);
    if (!$u->fetch()) {
        redirect($back . '?error=' . urlencode('Mitglied nicht gefunden.'));
    }

    // Enforce one-to-one: member profile may have at most one user per team
    $dup = $pdo->prepare(
        "SELECT id FROM users WHERE member_id = ? AND team_id = ? AND role = 'member'"
    );
    $dup->execute([$player_id, $team_id]);
    if ($dup->fetch()) {
        redirect($back . '?error=' . urlencode('Dieses Profil ist in deinem Team bereits mit einem Account verknüpft.'));
    }

    $pdo->prepare("UPDATE users SET member_id = ? WHERE id = ? AND team_id = ? AND role = 'member'")
        ->execute([$player_id, $user_id, $team_id]);

    redirect($back);

} elseif ($action === 'unlink-user') {
    // member_id is NOT NULL — every user must be linked to a member profile.
    // To reassign, use /admin/players or delete and recreate the member account.
    redirect($back . '?error=' . urlencode('Verknüpfung kann nicht aufgehoben werden — jedes Mitglied benötigt ein Profil.'));

} else {
    redirect($back);
}
