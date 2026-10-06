<?php
// src/member/event_edit_handler.php — GET+POST /member/events/{id}/edit
// Nur eigene Termine und nur, solange das Team Mitglieder-Termine erlaubt (auch per RLS).

declare(strict_types=1);

require_member();
require_once ROOT_PATH . '/src/db/events.php';

$event_id = (int)($_REQUEST['event_id'] ?? 0);
$pdo      = get_db();
$team_id  = (int)$_SESSION['team_id'];
$event    = event_load($pdo, $event_id, $team_id);
if (!$event) redirect('/member/lists');
if (!event_member_may_edit($pdo, $event, (int)$_SESSION['user_id'])) redirect('/member/events/' . $event_id);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $input = event_input(true, false);
    $error = $input['error'];
    if ($error === '') {
        $resource_ids = resources_from_post();
        event_update($pdo, $team_id, $event_id, $input['fields'], $resource_ids);
        $conflicts = $resource_ids ? resources_conflict_count($pdo, 'event', [$event_id]) : 0;
        redirect('/member/events/' . $event_id . '?success=1' . ($conflicts ? '&conflicts=' . $conflicts : ''));
    }
}

$event_role         = 'member';
$resources          = resources_active($pdo);
$resource_selected  = $_SERVER['REQUEST_METHOD'] === 'POST' ? resources_from_post() : resources_booked_ids($pdo, 'event', $event_id);
$resource_conflicts = resources_conflicts($pdo, 'event', $event_id);
$resource_names     = resources_booked_names($pdo, 'event', $event_id);

require ROOT_PATH . '/src/templates/member/layout.php';

render_member_page('Termin bearbeiten', 'lists', function() use ($error, $event, $event_role, $resources, $resource_selected, $resource_conflicts, $resource_names) {
    if ($error) render_flash('error', $error);
    require ROOT_PATH . '/src/templates/components/event_form.php';
});
