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

$check = $pdo->prepare("SELECT id, first_name, last_name FROM members WHERE id = ?");
$check->execute([$player_id]);
$player = $check->fetch();

if (!$player) {
    redirect('/admin/players');
}

if ($action === 'edit') {
    $first_name   = trim($_POST['first_name']   ?? '');
    $last_name    = trim($_POST['last_name']    ?? '');
    $club_id      = (int)($_POST['club_id']     ?? 0);
    $email_raw    = trim($_POST['email']        ?? '');
    $phone        = trim($_POST['phone']        ?? '');
    $contact_name  = trim($_POST['contact_name']  ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $description   = trim($_POST['description']   ?? '');

    if (empty($first_name) || empty($last_name)) {
        redirect('/admin/players?error=' . urlencode('Vor- und Nachname sind erforderlich.'));
    }
    if ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        redirect('/admin/players?error=' . urlencode('Ungültige E-Mail-Adresse.'));
    }
    if ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        redirect('/admin/players?error=' . urlencode('Ungültige Kontakt-E-Mail-Adresse.'));
    }

    $pdo->prepare(
        "UPDATE members SET club_id = ?, first_name = ?, last_name = ?, email = ?,
                            phone = ?, contact_name = ?, contact_phone = ?, contact_email = ?, description = ?
         WHERE id = ?"
    )->execute([
        $club_id > 0 ? $club_id : null,
        $first_name, $last_name,
        $email_raw !== '' ? $email_raw : null,
        $phone !== '' ? $phone : null,
        $contact_name !== '' ? $contact_name : null,
        $contact_phone !== '' ? $contact_phone : null,
        $contact_email !== '' ? $contact_email : null,
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
        "SELECT id, team_id FROM users WHERE id = ? AND role = 'member' AND member_id IS NULL"
    );
    $user_check->execute([$user_id]);
    $target_user = $user_check->fetch();
    if (!$target_user) {
        redirect('/admin/players?error=' . urlencode('Account nicht gefunden oder bereits mit einem Profil verknüpft.'));
    }

    // Enforce one-to-one: member profile may have at most one user per team
    $dup = $pdo->prepare(
        "SELECT id FROM users WHERE member_id = ? AND team_id = ? AND role = 'member'"
    );
    $dup->execute([$player_id, (int)$target_user['team_id']]);
    if ($dup->fetch()) {
        redirect('/admin/players?error=' . urlencode('Dieses Profil ist in diesem Team bereits mit einem Account verknüpft.'));
    }

    $pdo->prepare("UPDATE users SET member_id = ? WHERE id = ? AND role = 'member'")
        ->execute([$player_id, $user_id]);

    redirect('/admin/players');

} elseif ($action === 'unlink-user') {
    // player_id is NOT NULL after migration 024 — every user must remain linked to a player.
    redirect('/admin/players?error=' . urlencode('Verknüpfung kann nicht aufgehoben werden — jedes Mitglied benötigt ein Mitgliedsprofil.'));

} elseif ($action === 'deactivate') {
    $pdo->prepare("UPDATE members SET is_active = FALSE WHERE id = ?")
        ->execute([$player_id]);
    redirect('/admin/players?success=' . urlencode('Profil deaktiviert.'));

} elseif ($action === 'reactivate') {
    $pdo->prepare("UPDATE members SET is_active = TRUE WHERE id = ?")
        ->execute([$player_id]);
    redirect('/admin/players?success=' . urlencode('Mitglied reaktiviert.'));

} elseif ($action === 'delete') {
    // Safety check: only deactivated profiles can be deleted
    $chk = $pdo->prepare("SELECT is_active FROM members WHERE id = ?");
    $chk->execute([$player_id]);
    $p = $chk->fetch();
    if (!$p || $p['is_active']) {
        redirect('/admin/players/' . $player_id . '/edit?error=' . urlencode('Nur deaktivierte Profile können gelöscht werden.'));
    }
    // Safety check: member profile must have no linked user accounts
    $linked = $pdo->prepare("SELECT COUNT(*) FROM users WHERE member_id = ?");
    $linked->execute([$player_id]);
    if ((int)$linked->fetchColumn() > 0) {
        redirect('/admin/players/' . $player_id . '/edit?error=' . urlencode('Dieses Profil ist noch mit Benutzerkonten verknüpft. Konten zuerst löschen.'));
    }
    $pdo->prepare("DELETE FROM members WHERE id = ? AND is_active = FALSE")->execute([$player_id]);
    redirect('/admin/players?success=' . urlencode('Mitglied gelöscht.'));

} else {
    redirect('/admin/players');
}
