<?php
// src/coordinator/player_edit_handler.php — GET+POST /coordinator/players/{id}/edit

declare(strict_types=1);

require_coordinator();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
if ($player_id <= 0) redirect('/coordinator/members');

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
            "UPDATE players SET first_name=?, last_name=?, email=?, phone=?,
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
            $player_id,
        ]);
        reset_rls_context($pdo);
        set_team_context($pdo, $team_id, 'coordinator', $user_id);

        redirect('/coordinator/players/' . $player_id . '?success=1');
    }

    reset_rls_context($pdo);
    set_team_context($pdo, $team_id, 'coordinator', $user_id);
}

set_admin_context($pdo);

$p_stmt = $pdo->prepare(
    "SELECT p.*, c.name AS club_name
     FROM players p
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE p.id = ?"
);
$p_stmt->execute([$player_id]);
$player = $p_stmt->fetch();
if (!$player) redirect('/coordinator/members');

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();

reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', $user_id);

if ($error) {
    $player = array_merge($player, [
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

render_coach_page('Spieler bearbeiten', 'members', function() use ($player, $player_id, $clubs, $error) {
    require ROOT_PATH . '/src/templates/coordinator/player_edit.php';
});
