<?php
// src/coordinator/event_create_handler.php — GET+POST /coordinator/events/create

declare(strict_types=1);

require_coordinator();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title      = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon       = trim($_POST['icon'] ?? 'bi-calendar-event');
    $date       = trim($_POST['date'] ?? '');
    $is_all_day = !empty($_POST['is_all_day']);
    $time_start = trim($_POST['time_start'] ?? '');
    $time_end   = trim($_POST['time_end'] ?? '');
    $visibility = in_array($_POST['visibility'] ?? '', ['protected', 'private'], true)
        ? $_POST['visibility'] : 'protected';

    if ($title === '' || mb_strlen($title) > 200) {
        $error = 'Titel erforderlich (max. 200 Zeichen).';
    } elseif ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = 'Datum erforderlich.';
    } else {
        $pdo = get_db();
        $team_id = (int)$_SESSION['team_id'];
        set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

        $pdo->prepare(
            "INSERT INTO events (team_id, title, description, icon, date, is_all_day, time_start, time_end, visibility)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $team_id,
            $title,
            $description !== '' ? $description : null,
            $icon !== '' ? $icon : 'bi-calendar-event',
            $date,
            $is_all_day ? 'true' : 'false',
            (!$is_all_day && $time_start !== '') ? $time_start : null,
            (!$is_all_day && $time_end !== '') ? $time_end : null,
            $visibility,
        ]);
        redirect('/coordinator/lists?success=1');
    }
}

$pdo = $pdo ?? get_db();
$event = null;

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Termin erstellen', 'lists', function() use ($error, $event) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/event_form.php';
});
