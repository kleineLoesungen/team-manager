<?php
// src/coordinator/event_create_handler.php — GET+POST /coordinator/events/create
// Einzelner Termin oder Serie (eigenständige Termine bis zu einem Enddatum), src/db/events.php.

declare(strict_types=1);

require_coordinator();
require_once ROOT_PATH . '/src/db/events.php';

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $input = event_input(false, true);
    $error = $input['error'];
    if ($error === '') {
        $resource_ids = resources_from_post();
        $ids = event_create($pdo, $team_id, (int)$_SESSION['user_id'], $input['fields'], $input['dates'], $resource_ids);
        event_saved_redirect($pdo, 'coordinator', $ids, $resource_ids, true);
    }
}

$event              = null;
$event_role         = 'coordinator';
$resources          = resources_active($pdo);
$resource_selected  = $_SERVER['REQUEST_METHOD'] === 'POST' ? resources_from_post() : [];
$resource_conflicts = [];
$resource_names     = [];

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Termin erstellen', 'contents', function() use ($error, $event, $event_role, $resources, $resource_selected, $resource_conflicts, $resource_names) {
    if ($error) render_flash('error', $error);
    require ROOT_PATH . '/src/templates/components/event_form.php';
});
