<?php
// src/member/switch_team_handler.php — GET/POST /member/switch-team
// Members have one account per team. Accounts of the same member profile (users.member_id)
// can switch to each other without signing in again (src/db/team_switch.php).

declare(strict_types=1);

require_member();
require_once ROOT_PATH . '/src/db/team_switch.php';

$pdo     = get_db();
$options = team_switch_options($pdo);
if (count($options) < 2) {
    redirect('/member/contents');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $team_id = (int)($_POST['team_id'] ?? 0);
    $target  = current(array_filter($options, fn($o) => $o['team_id'] === $team_id));
    if (!$target) {
        redirect('/member/switch-team?error=1');
    }

    team_switch_apply($pdo, $target);   // Konto desselben Profils im anderen Team übernehmen
    redirect('/member/contents');
}

$available_teams = $options;
$is_switch       = true;
$error           = !empty($_GET['error']);
$form_action     = '/member/switch-team';
$back_url        = '/member/contents';
require ROOT_PATH . '/src/templates/coordinator/select_team.php';
