<?php
// src/admin/member_action_handler.php — POST: edit, link-user, or deactivate/reactivate/delete a member profile
// $_REQUEST['profile_id'] and $_REQUEST['action'] set by router

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/members');
}

require_csrf();

$profile_id = (int)($_REQUEST['profile_id'] ?? 0);
$action     = $_REQUEST['action'] ?? '';

if ($profile_id <= 0) {
    redirect('/admin/members');
}

$pdo = get_db();

$check = $pdo->prepare("SELECT id, first_name, last_name FROM members WHERE id = ?");
$check->execute([$profile_id]);
$profile = $check->fetch();

if (!$profile) {
    redirect('/admin/members');
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
        redirect('/admin/members?error=' . urlencode('Vor- und Nachname sind erforderlich.'));
    }
    if ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        redirect('/admin/members?error=' . urlencode('Ungültige E-Mail-Adresse.'));
    }
    if ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        redirect('/admin/members?error=' . urlencode('Ungültige Kontakt-E-Mail-Adresse.'));
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
        $profile_id,
    ]);
    redirect('/admin/members?success=' . urlencode(
        $first_name . ' ' . $last_name . ' gespeichert.'
    ));

} elseif ($action === 'link-user') {
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        redirect('/admin/members?error=' . urlencode('Kein Account ausgewählt.'));
    }

    $user_check = $pdo->prepare(
        "SELECT id, team_id FROM users WHERE id = ? AND role = 'member' AND member_id IS NULL"
    );
    $user_check->execute([$user_id]);
    $target_user = $user_check->fetch();
    if (!$target_user) {
        redirect('/admin/members?error=' . urlencode('Account nicht gefunden oder bereits mit einem Profil verknüpft.'));
    }

    $dup = $pdo->prepare(
        "SELECT id FROM users WHERE member_id = ? AND team_id = ? AND role = 'member'"
    );
    $dup->execute([$profile_id, (int)$target_user['team_id']]);
    if ($dup->fetch()) {
        redirect('/admin/members?error=' . urlencode('Dieses Profil ist in diesem Team bereits mit einem Account verknüpft.'));
    }

    $pdo->prepare("UPDATE users SET member_id = ? WHERE id = ? AND role = 'member'")
        ->execute([$profile_id, $user_id]);

    redirect('/admin/members');

} elseif ($action === 'unlink-user') {
    redirect('/admin/members?error=' . urlencode('Verknüpfung kann nicht aufgehoben werden — jedes Mitglied benötigt ein Mitgliedsprofil.'));

} elseif ($action === 'deactivate') {
    $pdo->prepare("UPDATE members SET is_active = FALSE WHERE id = ?")
        ->execute([$profile_id]);
    redirect('/admin/members?success=' . urlencode('Profil deaktiviert.'));

} elseif ($action === 'reactivate') {
    $pdo->prepare("UPDATE members SET is_active = TRUE WHERE id = ?")
        ->execute([$profile_id]);
    redirect('/admin/members?success=' . urlencode('Mitglied reaktiviert.'));

} elseif ($action === 'delete') {
    $chk = $pdo->prepare("SELECT is_active FROM members WHERE id = ?");
    $chk->execute([$profile_id]);
    $p = $chk->fetch();
    if (!$p || $p['is_active']) {
        redirect('/admin/members/' . $profile_id . '/edit?error=' . urlencode('Nur deaktivierte Profile können gelöscht werden.'));
    }
    $linked = $pdo->prepare("SELECT COUNT(*) FROM users WHERE member_id = ?");
    $linked->execute([$profile_id]);
    if ((int)$linked->fetchColumn() > 0) {
        redirect('/admin/members/' . $profile_id . '/edit?error=' . urlencode('Dieses Profil ist noch mit Benutzerkonten verknüpft. Konten zuerst löschen.'));
    }
    $pdo->prepare("DELETE FROM members WHERE id = ? AND is_active = FALSE")->execute([$profile_id]);
    redirect('/admin/members?success=' . urlencode('Mitglied gelöscht.'));

} else {
    redirect('/admin/members');
}
