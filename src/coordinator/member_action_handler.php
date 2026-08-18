<?php
// src/coordinator/member_action_handler.php — POST: member actions for coordinator
// Actions: reset-password (AUTH-03), deactivate, reactivate
// $_REQUEST['member_id'] and $_REQUEST['action'] set by router (public/index.php)

declare(strict_types=1);

require_coordinator();
require ROOT_PATH . '/src/templates/coordinator/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/coordinator/members');
}

require_csrf();

$member_id = (int)($_REQUEST['member_id'] ?? 0);
$action    = $_REQUEST['action'] ?? '';

if ($member_id <= 0 || !in_array($action, ['reset-password', 'deactivate', 'reactivate', 'delete'], true)) {
    redirect('/coordinator/members');
}

$pdo      = get_db();
$team_id  = (int)$_SESSION['team_id'];

// Optional caller-supplied return URL — must be a relative path (no protocol-relative tricks)
$back_raw = trim($_POST['_back'] ?? '');
$back_url = ($back_raw !== '' && $back_raw[0] === '/' && (!isset($back_raw[1]) || $back_raw[1] !== '/'))
    ? $back_raw
    : '/coordinator/members';

// Ownership check: member must belong to this coordinator's team AND be role='member'
$check = $pdo->prepare(
    "SELECT u.id, u.username, u.is_active, p.first_name, p.last_name
     FROM users u
     JOIN players p ON p.id = u.player_id
     WHERE u.id = ? AND u.team_id = ? AND u.role = 'member'"
);
$check->execute([$member_id, $team_id]);
$member = $check->fetch();

if (!$member) {
    redirect('/coordinator/members');
}

try {
    switch ($action) {

        case 'reset-password':
            // AUTH-03: generate new password, display once in credential modal
            $plain_password = generate_random_password();
            $password_hash  = password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare(
                "UPDATE users SET password_hash = ? WHERE id = ? AND team_id = ? AND role = 'member'"
            );
            $stmt->execute([$password_hash, $member_id, $team_id]);

            // NEVER log $plain_password — per Pitfall 2
            error_log('Password reset for member id=' . $member_id . ' (username=' . $member['username'] . ')');

            // Show credential modal — per D-08 (reuse admin pattern)
            $credential_username = $member['username'];
            $credential_password = $plain_password;
            $redirect_url        = $back_url;

            render_layout_head('Neue Anmeldedaten');
            render_navbar();
            require ROOT_PATH . '/src/templates/admin/credential_modal.php';
            render_layout_foot();
            exit;

        case 'deactivate':
            $stmt = $pdo->prepare(
                "UPDATE users SET is_active = FALSE WHERE id = ? AND team_id = ? AND role = 'member'"
            );
            $stmt->execute([$member_id, $team_id]);
            redirect($back_url);

        case 'reactivate':
            $stmt = $pdo->prepare(
                "UPDATE users SET is_active = TRUE WHERE id = ? AND team_id = ? AND role = 'member'"
            );
            $stmt->execute([$member_id, $team_id]);
            redirect($back_url);

        case 'delete':
            // Only deactivated members may be deleted; player record is kept.
            if ($member['is_active']) {
                redirect('/coordinator/members?error=' . urlencode('Nur deaktivierte Mitglieder können gelöscht werden.'));
            }
            set_admin_context($pdo);
            $pdo->prepare(
                "DELETE FROM users WHERE id = ? AND team_id = ? AND role = 'member' AND is_active = FALSE"
            )->execute([$member_id, $team_id]);
            reset_rls_context($pdo);
            set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);
            redirect('/coordinator/members?success=' . urlencode($member['first_name'] . ' ' . $member['last_name'] . ' wurde gelöscht.'));
    }

} catch (PDOException $e) {
    error_log('Member action error for member id=' . $member_id . ' action=' . $action . ': ' . $e->getMessage());
    redirect('/coordinator/members?error=' . urlencode('Ein Fehler ist aufgetreten. Bitte versuch es später erneut.'));
}
