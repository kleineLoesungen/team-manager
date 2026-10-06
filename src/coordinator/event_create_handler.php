<?php
// src/coordinator/event_create_handler.php — GET+POST /coordinator/events/create

declare(strict_types=1);

require_coordinator();
require_once ROOT_PATH . '/src/db/resources.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $icon        = trim($_POST['icon'] ?? 'bi-calendar-event');
    $date        = trim($_POST['date'] ?? '');
    $is_all_day  = !empty($_POST['is_all_day']);
    $time_start  = trim($_POST['time_start'] ?? '');
    $time_end    = trim($_POST['time_end'] ?? '');
    $visibility  = in_array($_POST['visibility'] ?? '', ['protected', 'private'], true)
        ? $_POST['visibility'] : 'protected';
    $is_hidden   = isset($_POST['is_hidden']) && $_POST['is_hidden'] === '1';

    if ($title === '' || mb_strlen($title) > 200) {
        $error = 'Titel erforderlich (max. 200 Zeichen).';
    } elseif ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = 'Datum erforderlich.';
    } else {
        $pdo = get_db();
        $team_id = (int)$_SESSION['team_id'];
        set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

        $stmt = $pdo->prepare(
            "INSERT INTO events (team_id, title, description, location, icon, date, is_all_day, time_start, time_end, visibility, is_hidden)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([
            $team_id,
            $title,
            $description !== '' ? $description : null,
            $location !== '' ? $location : null,
            $icon !== '' ? $icon : 'bi-calendar-event',
            $date,
            $is_all_day ? 'true' : 'false',
            (!$is_all_day && $time_start !== '') ? $time_start : null,
            (!$is_all_day && $time_end !== '') ? $time_end : null,
            $visibility,
            $is_hidden ? 'true' : 'false',
        ]);
        $event_id     = (int)$stmt->fetchColumn();
        $resource_ids = resources_from_post();
        resources_save($pdo, $team_id, 'event', $event_id, $resource_ids);
        $back = $_POST['_back'] ?? '';
        $back = preg_match('#^/coordinator/lists(\?[^<>"\']*)?$#', $back) ? $back : '/coordinator/lists';
        $conflicts = $resource_ids ? resources_conflict_count($pdo, 'event', [$event_id]) : 0;
        redirect($back . (str_contains($back, '?') ? '&' : '?') . 'success=1' . ($conflicts ? '&conflicts=' . $conflicts : ''));
    }
}

$pdo = $pdo ?? get_db();
$event = null;
$resources          = resources_active($pdo);
$resource_selected  = $_SERVER['REQUEST_METHOD'] === 'POST' ? resources_from_post() : [];
$resource_conflicts = [];

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Termin erstellen', 'lists', function() use ($error, $event, $resources, $resource_selected, $resource_conflicts) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/event_form.php';
});
