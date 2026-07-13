<?php
// src/coordinator/member_edit_email_handler.php — GET+POST /coordinator/members/{id}/edit-email
// Coordinator-only: set or update a member's email address.

declare(strict_types=1);

require_coordinator();

$member_id = (int)($_REQUEST['member_id'] ?? 0);
if ($member_id <= 0) {
    redirect('/coordinator/members');
}

$pdo = get_db();

// Triple-constraint ownership check: id + role='member' + team_id (coordinator's team)
$check = $pdo->prepare(
    "SELECT id, first_name, last_name, email FROM users WHERE id = ? AND role = 'member' AND team_id = ?"
);
$check->execute([$member_id, $_SESSION['team_id']]);
$member = $check->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    redirect('/coordinator/members');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email_raw = trim($_POST['email'] ?? '');

    if ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse. Bitte überprüfe deine Eingabe.';
    } elseif ($email_raw !== '' && mb_strlen($email_raw) > 255) {
        $error = 'E-Mail-Adresse zu lang (max. 255 Zeichen).';
    } else {
        $upd = $pdo->prepare(
            "UPDATE users SET email = ? WHERE id = ? AND role = 'member' AND team_id = ?"
        );
        $upd->execute([$email_raw !== '' ? $email_raw : null, $member_id, $_SESSION['team_id']]);
        redirect('/coordinator/members?success=' . urlencode(
            'E-Mail-Adresse für ' . $member['first_name'] . ' ' . $member['last_name'] . ' gespeichert.'
        ));
    }
}

if (!empty($_GET['success'])) {
    $success = e($_GET['success']);
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page(
    'E-Mail-Adresse bearbeiten — ' . e($member['first_name'] . ' ' . $member['last_name']),
    'members',
    function() use ($member, $error, $success) {
        require ROOT_PATH . '/src/templates/coordinator/member_edit_email.php';
    }
);
