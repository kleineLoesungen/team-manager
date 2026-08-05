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

} elseif ($action === 'add-team') {
    $new_team_id = (int)($_POST['team_id'] ?? 0);
    $settings_url = '/admin/coordinators/' . $coordinator_id . '/settings';

    if ($new_team_id <= 0) {
        redirect($settings_url . '?error=' . urlencode('Kein Team ausgewählt.'));
    }

    $team_check = $pdo->prepare("SELECT id FROM teams WHERE id = ? AND is_active = TRUE");
    $team_check->execute([$new_team_id]);
    if (!$team_check->fetch()) {
        redirect($settings_url . '?error=' . urlencode('Team nicht gefunden oder inaktiv.'));
    }

    try {
        $pdo->prepare(
            "INSERT INTO coordinator_teams (user_id, team_id, joined_at) VALUES (?, ?, NOW())"
        )->execute([$coordinator_id, $new_team_id]);
        redirect($settings_url);
    } catch (PDOException $e) {
        error_log('Coordinator add-team error: ' . $e->getMessage());
        redirect($settings_url . '?error=' . urlencode('Koordinator ist bereits in diesem Team.'));
    }

} elseif ($action === 'set-club') {
    $club_id      = (int)($_POST['club_id'] ?? 0);
    $settings_url = '/admin/coordinators/' . $coordinator_id . '/settings';

    if ($club_id > 0) {
        $club_check = $pdo->prepare("SELECT id FROM clubs WHERE id = ? AND is_active = TRUE");
        $club_check->execute([$club_id]);
        if (!$club_check->fetch()) {
            redirect($settings_url . '?error=' . urlencode('Verein nicht gefunden oder inaktiv.'));
        }
        $pdo->prepare("UPDATE users SET club_id = ? WHERE id = ? AND role = 'coordinator'")
            ->execute([$club_id, $coordinator_id]);
    } else {
        $pdo->prepare("UPDATE users SET club_id = NULL WHERE id = ? AND role = 'coordinator'")
            ->execute([$coordinator_id]);
    }
    redirect($settings_url . '?success=' . urlencode('Verein gespeichert.'));

} elseif ($action === 'delete') {
    // Safety guard: only allow deleting deactivated coordinators
    $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'coordinator' AND is_active = FALSE");
    $check->execute([$coordinator_id]);
    if (!$check->fetch()) {
        redirect('/admin/coordinators?error=' . urlencode('Nur deaktivierte Koordinatoren können gelöscht werden.'));
    }

    // Cascade deletes: coordinator_teams rows are removed via ON DELETE CASCADE
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'coordinator' AND is_active = FALSE")
        ->execute([$coordinator_id]);

    redirect('/admin/coordinators?success=' . urlencode('Koordinator wurde gelöscht.'));

} elseif ($action === 'remove-team') {
    $team_id_to_remove = (int)($_POST['team_id'] ?? 0);
    $settings_url      = '/admin/coordinators/' . $coordinator_id . '/settings';
    if ($team_id_to_remove <= 0) redirect($settings_url);

    $pdo->prepare(
        "UPDATE coordinator_teams SET left_at = NOW()
         WHERE user_id = ? AND team_id = ? AND left_at IS NULL"
    )->execute([$coordinator_id, $team_id_to_remove]);

    redirect($settings_url);

} else {
    redirect('/admin/coordinators');
}
