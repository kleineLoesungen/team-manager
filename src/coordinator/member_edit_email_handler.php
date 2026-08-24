<?php
// src/coordinator/member_edit_email_handler.php — GET+POST /coordinator/members/{id}/edit-email
// Coordinator-only: set or update a member's email address via their player record.

declare(strict_types=1);

require_coordinator();

$member_id = (int)($_REQUEST['member_id'] ?? 0);
if ($member_id <= 0) {
    redirect('/coordinator/members');
}

$pdo = get_db();

// Triple-constraint ownership check: id + role='member' + team_id
$check = $pdo->prepare(
    "SELECT u.id, u.member_id, p.first_name, p.last_name, p.email
     FROM users u
     JOIN members p ON p.id = u.member_id
     WHERE u.id = ? AND u.role = 'member' AND u.team_id = ?"
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
        $email_val = $email_raw !== '' ? $email_raw : null;

        // Write to members (canonical person table) — needs admin context
        set_admin_context($pdo);
        $pdo->prepare("UPDATE members SET email = ? WHERE id = ?")
            ->execute([$email_val, (int)$member['member_id']]);
        reset_rls_context($pdo);
        set_team_context($pdo, (int)$_SESSION['team_id'], 'coordinator', (int)$_SESSION['user_id']);

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
