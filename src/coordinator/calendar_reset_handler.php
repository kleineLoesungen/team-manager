<?php
// src/coordinator/calendar_reset_handler.php - POST /coordinator/calendar-reset
// Regenerates one of the team's two calendar tokens (coordinator or member feed).
// Coordinators may rotate EITHER token - if a link leaks, they replace it (members cannot).

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/coordinator/profile');
}

require_csrf();

$pdo        = get_db();
$team_id    = (int)$_SESSION['team_id'];
$user_id    = (int)$_SESSION['user_id'];
$token_type = ($_POST['token_type'] ?? '') === 'member' ? 'member' : 'coordinator';
$column     = $token_type === 'member' ? 'calendar_token_member' : 'calendar_token_coordinator';
$token      = generate_calendar_token();

set_admin_context($pdo);
$pdo->prepare("UPDATE teams SET {$column} = ? WHERE id = ?")
    ->execute([$token, $team_id]);
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', $user_id);

redirect('/coordinator/profile?cal_reset=' . $token_type);
