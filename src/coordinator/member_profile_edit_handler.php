<?php
// src/coordinator/member_profile_edit_handler.php — GET+POST /coordinator/member-profiles/{id}/edit

declare(strict_types=1);

require_coordinator();

$profile_id = (int)($_REQUEST['profile_id'] ?? 0);
if ($profile_id <= 0) redirect('/coordinator/member-profiles');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$user_id = (int)$_SESSION['user_id'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $first_name    = trim($_POST['first_name']    ?? '');
    $last_name     = trim($_POST['last_name']     ?? '');
    $email_raw     = trim($_POST['email']         ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $contact_name  = trim($_POST['contact_name']  ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $description   = trim($_POST['description']   ?? '');
    $club_id       = (int)($_POST['club_id']      ?? 0);

    if (empty($first_name) || empty($last_name)) {
        $error = 'Vor- und Nachname sind erforderlich.';
    } elseif ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse.';
    } elseif ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige Kontakt-E-Mail-Adresse.';
    }

    if (!$error) {
        set_admin_context($pdo);
        $pdo->prepare(
            "UPDATE members SET first_name=?, last_name=?, email=?, phone=?,
              contact_name=?, contact_phone=?, contact_email=?, description=?, club_id=? WHERE id=?"
        )->execute([
            $first_name, $last_name,
            $email_raw !== '' ? $email_raw : null,
            $phone !== '' ? $phone : null,
            $contact_name !== '' ? $contact_name : null,
            $contact_phone !== '' ? $contact_phone : null,
            $contact_email !== '' ? $contact_email : null,
            $description !== '' ? $description : null,
            $club_id > 0 ? $club_id : null,
            $profile_id,
        ]);
        reset_rls_context($pdo);
        set_team_context($pdo, $team_id, 'coordinator', $user_id);

        redirect('/coordinator/member-profiles/' . $profile_id . '?success=1');
    }

    reset_rls_context($pdo);
    set_team_context($pdo, $team_id, 'coordinator', $user_id);
}

set_admin_context($pdo);

$p_stmt = $pdo->prepare(
    "SELECT p.*, c.name AS club_name
     FROM members p
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE p.id = ?"
);
$p_stmt->execute([$profile_id]);
$profile = $p_stmt->fetch();
if (!$profile) redirect('/coordinator/member-profiles');

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();

reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', $user_id);

if ($error) {
    $profile = array_merge($profile, [
        'first_name'    => $_POST['first_name']    ?? '',
        'last_name'     => $_POST['last_name']     ?? '',
        'email'         => $_POST['email']         ?? '',
        'phone'         => $_POST['phone']         ?? '',
        'contact_name'  => $_POST['contact_name']  ?? '',
        'contact_phone' => $_POST['contact_phone'] ?? '',
        'contact_email' => $_POST['contact_email'] ?? '',
        'description'   => $_POST['description']   ?? '',
        'club_id'       => (int)($_POST['club_id'] ?? 0),
    ]);
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Mitglied bearbeiten', 'members', function() use ($profile, $profile_id, $clubs, $error) {
    require ROOT_PATH . '/src/templates/coordinator/member_profile_edit.php';
});
