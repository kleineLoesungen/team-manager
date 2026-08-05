<?php
// src/admin/player_action_handler.php — POST: edit, link-user, or unlink-user for a player
// $_REQUEST['player_id'] and $_REQUEST['action'] set by router

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/players');
}

require_csrf();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
$action    = $_REQUEST['action'] ?? '';

if ($player_id <= 0) {
    redirect('/admin/players');
}

$pdo = get_db();

$check = $pdo->prepare("SELECT id, first_name, last_name FROM players WHERE id = ?");
$check->execute([$player_id]);
$player = $check->fetch();

if (!$player) {
    redirect('/admin/players');
}

if ($action === 'edit') {
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $club_id      = (int)($_POST['club_id'] ?? 0);
    $phone        = trim($_POST['phone'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (empty($first_name) || empty($last_name) || $club_id <= 0) {
        redirect('/admin/players?error=' . urlencode('Vor- und Nachname sowie Klub sind erforderlich.'));
    }

    $pdo->prepare(
        "UPDATE players SET club_id = ?, first_name = ?, last_name = ?,
                            phone = ?, contact_name = ?, description = ?
         WHERE id = ?"
    )->execute([
        $club_id, $first_name, $last_name,
        $phone !== '' ? $phone : null,
        $contact_name !== '' ? $contact_name : null,
        $description !== '' ? $description : null,
        $player_id,
    ]);
    redirect('/admin/players?success=' . urlencode(
        $first_name . ' ' . $last_name . ' gespeichert.'
    ));

} elseif ($action === 'link-user') {
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        redirect('/admin/players?error=' . urlencode('Kein Account ausgewählt.'));
    }

    // Verify the user is an unlinked active member and get their team
    $user_check = $pdo->prepare(
        "SELECT id, team_id FROM users WHERE id = ? AND role = 'member' AND player_id IS NULL"
    );
    $user_check->execute([$user_id]);
    $target_user = $user_check->fetch();
    if (!$target_user) {
        redirect('/admin/players?error=' . urlencode('Account nicht gefunden oder bereits mit einem Spieler verknüpft.'));
    }

    // Enforce one-to-one: player may have at most one user per team
    $dup = $pdo->prepare(
        "SELECT id FROM users WHERE player_id = ? AND team_id = ? AND role = 'member'"
    );
    $dup->execute([$player_id, (int)$target_user['team_id']]);
    if ($dup->fetch()) {
        redirect('/admin/players?error=' . urlencode('Dieser Spieler ist in diesem Team bereits mit einem Account verknüpft.'));
    }

    $pdo->prepare("UPDATE users SET player_id = ? WHERE id = ? AND role = 'member'")
        ->execute([$player_id, $user_id]);

    redirect('/admin/players');

} elseif ($action === 'unlink-user') {
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        redirect('/admin/players');
    }

    // Safety: only unlink if the user is currently linked to THIS player
    $pdo->prepare(
        "UPDATE users SET player_id = NULL WHERE id = ? AND player_id = ? AND role = 'member'"
    )->execute([$user_id, $player_id]);

    redirect('/admin/players');

} else {
    redirect('/admin/players');
}
