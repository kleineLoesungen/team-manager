<?php
// src/coordinator/event_edit_handler.php — GET+POST /coordinator/events/{id}/edit
// Koordinatoren bearbeiten alle Termine ihres Teams, auch die von Mitgliedern (src/db/events.php).

declare(strict_types=1);

require_coordinator();
require_once ROOT_PATH . '/src/db/events.php';

$event_id = (int)($_REQUEST['event_id'] ?? 0);
$pdo      = get_db();
$team_id  = (int)$_SESSION['team_id'];

$event = event_load($pdo, $event_id, $team_id);
if (!$event) redirect('/coordinator/contents');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $input = event_input(false, false);
    $error = $input['error'];
    if ($error === '') {
        $resource_ids = resources_from_post();
        event_update($pdo, $team_id, $event_id, $input['fields'], $resource_ids);
        event_saved_redirect($pdo, 'coordinator', [$event_id], $resource_ids, false);
    }
}

$event_role         = 'coordinator';
$resources          = resources_active($pdo);
$resource_selected  = $_SERVER['REQUEST_METHOD'] === 'POST' ? resources_from_post() : resources_booked_ids($pdo, 'event', $event_id);
$resource_conflicts = resources_conflicts($pdo, 'event', $event_id);
$resource_names     = resources_booked_names($pdo, 'event', $event_id);

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Termin bearbeiten', 'contents', function() use ($error, $event, $event_role, $resources, $resource_selected, $resource_conflicts, $resource_names) {
    if ($error) render_flash('error', $error);
    require ROOT_PATH . '/src/templates/components/event_form.php';
});
