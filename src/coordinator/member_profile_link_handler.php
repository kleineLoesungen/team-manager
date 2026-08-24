<?php
// src/coordinator/member_profile_link_handler.php — POST: link/unlink a member to a profile (my team only)

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/coordinator/member-profiles');
}

require_csrf();

$profile_id = (int)($_REQUEST['profile_id'] ?? 0);
$action     = $_REQUEST['action'] ?? '';

if ($profile_id <= 0) redirect('/coordinator/member-profiles');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];

$_back_raw = $_POST['_back'] ?? '';
$back = (str_starts_with($_back_raw, '/coordinator/member-profiles') && !str_contains($_back_raw, '//'))
    ? $_back_raw
    : '/coordinator/member-profiles/' . $profile_id;

set_admin_context($pdo);
$check = $pdo->prepare("SELECT id FROM members WHERE id = ?");
$check->execute([$profile_id]);
if (!$check->fetch()) redirect('/coordinator/member-profiles');
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

if ($action === 'link-user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) redirect($back);

    $u = $pdo->prepare(
        "SELECT id FROM users WHERE id = ? AND team_id = ? AND role = 'member' AND is_active = TRUE"
    );
    $u->execute([$user_id, $team_id]);
    if (!$u->fetch()) {
        redirect($back . '?error=' . urlencode('Mitglied nicht gefunden.'));
    }

    $dup = $pdo->prepare(
        "SELECT id FROM users WHERE member_id = ? AND team_id = ? AND role = 'member'"
    );
    $dup->execute([$profile_id, $team_id]);
    if ($dup->fetch()) {
        redirect($back . '?error=' . urlencode('Dieses Profil ist in deinem Team bereits mit einem Account verknüpft.'));
    }

    $pdo->prepare("UPDATE users SET member_id = ? WHERE id = ? AND team_id = ? AND role = 'member'")
        ->execute([$profile_id, $user_id, $team_id]);

    redirect($back);

} elseif ($action === 'unlink-user') {
    redirect($back . '?error=' . urlencode('Verknüpfung kann nicht aufgehoben werden — jedes Mitglied benötigt ein Profil.'));

} else {
    redirect($back);
}
