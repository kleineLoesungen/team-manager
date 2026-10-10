<?php
// src/member/calendar_reset_handler.php - POST /member/calendar-reset
// Renews the member's personal calendar link (members.calendar_token, src/db/calendar.php).
// The old link stops working at once - for all teams of the person.

declare(strict_types=1);

require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/member/contents?view=month');
}

require_csrf();
require_once ROOT_PATH . '/src/db/calendar.php';

$pdo  = get_db();
$stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$stmt->execute([(int)$_SESSION['user_id']]);
$member_id = (int)$stmt->fetchColumn();
if ($member_id > 0) {
    member_calendar_token($pdo, $member_id, true);
}

redirect(($_POST['return'] ?? '') === 'profile'
    ? '/member/profile?cal_reset=1#kalender-abo'
    : '/member/contents?view=month&cal_reset=1#kalender-abo');
