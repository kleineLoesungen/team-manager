<?php
// src/coordinator/select_team_handler.php — GET: team picker; POST: set active team
// Handles both /coordinator/select-team (post-login) and /coordinator/switch-team (in-session)

declare(strict_types=1);

// Guard: must have a valid user session (may have pending_team_pick OR be already logged in)
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'coordinator') {
    redirect('/login');
}
check_session_timeout();

$is_switch = str_ends_with($_SERVER['REQUEST_URI'] ?? '', 'switch-team');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $team_id = (int)($_POST['team_id'] ?? 0);

    // Validate: team_id must be in the allowed list stored in session
    $available = $_SESSION['available_teams'] ?? [];
    $valid = array_filter($available, fn($t) => (int)$t['team_id'] === $team_id);

    if (empty($valid) || $team_id <= 0) {
        redirect('/coordinator/select-team?error=1');
    }

    $team_name = current($valid)['team_name'];
    $pdo = get_db();
    set_admin_context($pdo);
    // Keep users.team_id in sync — critical for RLS context at next request
    $pdo->prepare("UPDATE users SET team_id = ? WHERE id = ?")->execute([$team_id, (int)$_SESSION['user_id']]);
    reset_rls_context($pdo);

    $_SESSION['team_id']   = $team_id;
    $_SESSION['team_name'] = $team_name;
    unset($_SESSION['pending_team_pick']);
    // Keep available_teams in session for switch-team functionality
    set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);
    redirect('/coordinator/contents');
}

// GET: Teams immer frisch laden (Zuordnungen können sich seit dem Login geändert haben)
if (!$is_switch && !empty($_SESSION['pending_team_pick']) && !empty($_SESSION['available_teams'])) {
    $available_teams = $_SESSION['available_teams'];   // direkt nach dem Login: Liste vom Login
} else {
    require_once ROOT_PATH . '/src/db/team_switch.php';
    $available_teams = team_switch_options(get_db());
    $_SESSION['available_teams'] = $available_teams;
}
$error       = !empty($_GET['error']);
$form_action = '/coordinator/select-team';
$back_url    = '/coordinator/contents';

require ROOT_PATH . '/src/templates/coordinator/select_team.php';
