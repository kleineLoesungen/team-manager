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
    redirect('/member/lists');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $team_id = (int)($_POST['team_id'] ?? 0);
    $target  = current(array_filter($options, fn($o) => $o['team_id'] === $team_id));
    if (!$target) {
        redirect('/member/switch-team?error=1');
    }

    set_admin_context($pdo);
    $stmt = $pdo->prepare("SELECT confirmed_at FROM users WHERE id = ?");
    $stmt->execute([$target['user_id']]);
    $confirmed_at = $stmt->fetchColumn();
    reset_rls_context($pdo);

    // Anderes Konto übernehmen: neue Sitzungs-ID, dann die Sitzung auf dieses Konto setzen
    session_regenerate_id(true);
    $_SESSION['user_id']       = $target['user_id'];
    $_SESSION['team_id']       = $target['team_id'];
    $_SESSION['team_name']     = $target['team_name'];
    $_SESSION['confirmed_at']  = $confirmed_at ?: null;
    $_SESSION['last_activity'] = time();
    redirect('/member/lists');
}

$available_teams = $options;
$is_switch       = true;
$error           = !empty($_GET['error']);
$form_action     = '/member/switch-team';
$back_url        = '/member/lists';
require ROOT_PATH . '/src/templates/coordinator/select_team.php';
