<?php
// src/auth/open_handler.php — GET /open?team={id}&list={id} | &file={id}
// Target of push notifications (src/push/notify_push.php). A person in several teams may get
// the notification on a device signed in to another team: if the signed-in person also
// belongs to the content's team, the session switches there first (as the team switcher
// does), then the list or file opens in the role of the signed-in account.

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/team_switch.php';

$team_id = (int)($_GET['team'] ?? 0);
[$type, $id] = isset($_GET['file']) ? ['files', (int)$_GET['file']] : ['lists', (int)($_GET['list'] ?? 0)];

$role = $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || !in_array($role, ['coordinator', 'member'], true)) {
    redirect('/login?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
}
$role === 'coordinator' ? require_coordinator() : require_member();

if ($team_id > 0 && $team_id !== (int)($_SESSION['team_id'] ?? 0)) {
    $pdo    = get_db();
    $target = current(array_filter(team_switch_options($pdo), fn($o) => $o['team_id'] === $team_id));
    if ($target) team_switch_apply($pdo, $target);
}

redirect($id > 0 ? "/{$role}/{$type}/{$id}" : "/{$role}/contents");
