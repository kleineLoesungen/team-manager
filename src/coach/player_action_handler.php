<?php
// src/coach/player_action_handler.php — POST: player actions for coach
// Actions: reset-password (AUTH-03), deactivate, reactivate
// $_REQUEST['player_id'] and $_REQUEST['action'] set by router (public/index.php)

declare(strict_types=1);

require_coach();
require ROOT_PATH . '/src/templates/coach/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/moderator/members');
}

require_csrf();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
$action    = $_REQUEST['action'] ?? '';

if ($player_id <= 0 || !in_array($action, ['reset-password', 'deactivate', 'reactivate'], true)) {
    redirect('/moderator/members');
}

$pdo      = get_db();
$team_id  = (int)$_SESSION['team_id'];

// Ownership check: player must belong to this coach's team AND be role='player'
// Prevents acting on players from other teams even if team_id RLS is bypassed.
$check = $pdo->prepare(
    "SELECT id, username, first_name, last_name
     FROM users
     WHERE id = ? AND team_id = ? AND role = 'member'"
);
$check->execute([$player_id, $team_id]);
$player = $check->fetch();

if (!$player) {
    redirect('/moderator/members');
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
            $stmt->execute([$password_hash, $player_id, $team_id]);

            // NEVER log $plain_password — per Pitfall 2
            error_log('Password reset for player id=' . $player_id . ' (username=' . $player['username'] . ')');

            // Show credential modal — per D-08 (reuse admin pattern)
            $credential_username = $player['username'];
            $credential_password = $plain_password;
            $redirect_url        = '/moderator/members';

            render_layout_head('Neue Anmeldedaten');
            render_navbar();
            require ROOT_PATH . '/src/templates/admin/credential_modal.php';
            render_layout_foot();
            exit;

        case 'deactivate':
            $stmt = $pdo->prepare(
                "UPDATE users SET is_active = FALSE WHERE id = ? AND team_id = ? AND role = 'member'"
            );
            $stmt->execute([$player_id, $team_id]);
            redirect('/moderator/members');

        case 'reactivate':
            $stmt = $pdo->prepare(
                "UPDATE users SET is_active = TRUE WHERE id = ? AND team_id = ? AND role = 'member'"
            );
            $stmt->execute([$player_id, $team_id]);
            redirect('/moderator/members');
    }

} catch (PDOException $e) {
    error_log('Player action error for player id=' . $player_id . ' action=' . $action . ': ' . $e->getMessage());
    redirect('/moderator/members?error=' . urlencode('Ein Fehler ist aufgetreten. Bitte versuch es später erneut.'));
}
