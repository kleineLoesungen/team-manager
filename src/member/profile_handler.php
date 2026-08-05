<?php
// src/member/profile_handler.php — GET+POST /member/profile
// Allows members to save or clear their optional email address.
// Per D-01 (Phase 5): email is used by coordinators to send notifications.
// Per UI spec Screen 3: email field is optional, validated via filter_var.

declare(strict_types=1);

require_member();

$pdo    = get_db();
$user_id = (int)$_SESSION['user_id'];

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email_raw = trim($_POST['email'] ?? '');

    // Validation: empty is allowed (clears email); non-empty must be valid
    if ($email_raw !== '') {
        if (!filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
            redirect('/member/profile?error=invalid_email');
        }
        if (mb_strlen($email_raw) > 255) {
            redirect('/member/profile?error=invalid_email');
        }
    }

    // Save email (null clears it)
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ? AND role = 'member'");
    $stmt->execute([$email_raw !== '' ? $email_raw : null, $user_id]);

    redirect('/member/profile?success=1');
}

// GET: load current email from DB
$user_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$current_email = $user['email'] ?? '';

// Handle error and success from PRG query params
if (!empty($_GET['error']) && $_GET['error'] === 'invalid_email') {
    $error = 'Ungültige E-Mail-Adresse. Bitte überprüfe deine Eingabe.';
    // Retain submitted value so user doesn't have to retype
    $current_email = trim($_GET['submitted_email'] ?? $current_email);
}
if (!empty($_GET['success'])) {
    $success = 'E-Mail-Adresse gespeichert.';
}

require ROOT_PATH . '/src/templates/member/layout.php';

render_player_page('Team-Profil', 'profile', function() use ($current_email, $error, $success) {
    require ROOT_PATH . '/src/templates/member/profile.php';
});
