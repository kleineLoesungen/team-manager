<?php
// src/coordinator/calendar_reset_handler.php - POST /coordinator/calendar-reset
// Regenerates the team's coordinator calendar token - if the link leaks, a coordinator replaces it.
// Members have personal links instead (POST /member/calendar-reset).

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/coordinator/profile');
}

require_csrf();

$pdo        = get_db();
$team_id    = (int)$_SESSION['team_id'];
$user_id    = (int)$_SESSION['user_id'];
$token      = generate_calendar_token();

set_admin_context($pdo);
$pdo->prepare("UPDATE teams SET calendar_token_coordinator = ? WHERE id = ?")
    ->execute([$token, $team_id]);
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', $user_id);

redirect('/coordinator/profile?cal_reset=1');
