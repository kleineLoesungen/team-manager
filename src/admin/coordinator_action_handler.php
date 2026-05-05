<?php
// src/admin/coach_action_handler.php — POST: reset coach password, deactivate, or reactivate (AUTH-04)
// $_REQUEST['coordinator_id'] and $_REQUEST['action'] set by router

declare(strict_types=1);

require_admin();
require ROOT_PATH . '/src/templates/admin/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/coordinators');
}

require_csrf();

$coordinator_id = (int)($_REQUEST['coordinator_id'] ?? 0);
$action   = $_REQUEST['action'] ?? '';

if ($coordinator_id <= 0) {
    redirect('/admin/coordinators');
}

$pdo = get_db();

// Verify coach exists and is a coach role
$check = $pdo->prepare(
    "SELECT id, username, first_name, last_name FROM users WHERE id = ? AND role = 'coordinator'"
);
$check->execute([$coordinator_id]);
$coach = $check->fetch();

if (!$coach) {
    redirect('/admin/coordinators');
}

if ($action === 'reset-password') {
    try {
        $plain_password = generate_random_password();
        $password_hash  = password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'coordinator'");
        $stmt->execute([$password_hash, $coordinator_id]);

        // NEVER log $plain_password — per Pitfall 2
        error_log('Password reset for coach id=' . $coordinator_id . ' (username=' . $coach['username'] . ')');

        // Show credential modal for 60 seconds — per AUTH-04
        $credential_username = $coach['username'];
        $credential_password = $plain_password;
        $redirect_url        = '/admin/coordinators';

        render_layout_head('Neue Anmeldedaten');
        render_navbar();
        require ROOT_PATH . '/src/templates/admin/credential_modal.php';
        render_layout_foot();
        exit;

    } catch (PDOException $e) {
        error_log('Password reset error for coach id=' . $coordinator_id . ': ' . $e->getMessage());
        redirect('/admin/coordinators?error=' . urlencode('Ein Fehler ist aufgetreten. Bitte versuch es später erneut.'));
    }

} elseif ($action === 'deactivate') {
    $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ? AND role = 'coordinator'");
    $stmt->execute([$coordinator_id]);
    redirect('/admin/coordinators');

} elseif ($action === 'reactivate') {
    $stmt = $pdo->prepare("UPDATE users SET is_active = TRUE WHERE id = ? AND role = 'coordinator'");
    $stmt->execute([$coordinator_id]);
    redirect('/admin/coordinators');

} else {
    redirect('/admin/coordinators');
}
