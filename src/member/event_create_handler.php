<?php
// src/member/event_create_handler.php — GET+POST /member/events/create
// Nur wenn das Team Mitglieder-Termine erlaubt; ohne Serie, immer für das Team sichtbar.

declare(strict_types=1);

require_member();
require_once ROOT_PATH . '/src/db/events.php';

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
if (!events_members_may_create($pdo, $team_id)) {
    redirect('/member/contents?error=' . urlencode('In diesem Team legen nur Koordinatoren Termine an.'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $input = event_input(true, false);
    $error = $input['error'];
    if ($error === '') {
        $resource_ids = resources_from_post();
        $ids = event_create($pdo, $team_id, (int)$_SESSION['user_id'], $input['fields'], $input['dates'], $resource_ids);
        event_saved_redirect($pdo, 'member', $ids, $resource_ids, true);
    }
}

$event              = null;
$event_role         = 'member';
$resources          = resources_active($pdo);
$resource_selected  = $_SERVER['REQUEST_METHOD'] === 'POST' ? resources_from_post() : [];
$resource_conflicts = [];
$resource_names     = [];

require ROOT_PATH . '/src/templates/member/layout.php';

render_member_page('Termin anlegen', 'contents', function() use ($error, $event, $event_role, $resources, $resource_selected, $resource_conflicts, $resource_names) {
    if ($error) render_flash('error', $error);
    require ROOT_PATH . '/src/templates/components/event_form.php';
});
