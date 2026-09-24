<?php
// src/member/calendar_reset_handler.php — POST /member/calendar-reset
// Regenerates the calendar token for the current member.

declare(strict_types=1);

require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/member/profile');
}

require_csrf();

$pdo     = get_db();
$user_id = (int)$_SESSION['user_id'];
$token   = generate_calendar_token();

set_admin_context($pdo);
$pdo->prepare("UPDATE users SET calendar_token = ? WHERE id = ?")
    ->execute([$token, $user_id]);
reset_rls_context($pdo);
set_team_context($pdo, (int)$_SESSION['team_id'], 'member', $user_id);

redirect('/member/profile?cal_reset=1');
