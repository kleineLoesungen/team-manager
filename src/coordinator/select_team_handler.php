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
    redirect('/coordinator/members');
}

// GET: populate available_teams if not already set (switch-team case)
if (empty($_SESSION['available_teams'])) {
    $pdo = get_db();
    set_admin_context($pdo);
    $ct_stmt = $pdo->prepare(
        "SELECT ct.team_id, t.name AS team_name
         FROM coordinator_teams ct
         JOIN teams t ON t.id = ct.team_id
         WHERE ct.user_id = ? AND ct.left_at IS NULL AND t.is_active = TRUE
         ORDER BY ct.joined_at DESC"
    );
    $ct_stmt->execute([(int)$_SESSION['user_id']]);
    $_SESSION['available_teams'] = array_map(fn($t) => [
        'team_id'   => (int)$t['team_id'],
        'team_name' => $t['team_name'],
    ], $ct_stmt->fetchAll());
    reset_rls_context($pdo);
}

$available_teams = $_SESSION['available_teams'];
$error = !empty($_GET['error']);

require ROOT_PATH . '/src/templates/coordinator/select_team.php';
