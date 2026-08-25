<?php
// src/coordinator/event_edit_handler.php — GET+POST /coordinator/events/{id}/edit

declare(strict_types=1);

require_coordinator();

$event_id = (int)($_REQUEST['event_id'] ?? 0);
if ($event_id <= 0) redirect('/coordinator/lists');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND team_id = ?");
$stmt->execute([$event_id, $team_id]);
$event = $stmt->fetch();
if (!$event) redirect('/coordinator/lists');

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
        $pdo->prepare(
            "UPDATE events SET title=?, description=?, location=?, icon=?, date=?, is_all_day=?, time_start=?, time_end=?, visibility=?, is_hidden=?
             WHERE id=? AND team_id=?"
        )->execute([
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
            $event_id,
            $team_id,
        ]);
        $back = $_POST['_back'] ?? '';
        $back = preg_match('#^/coordinator/lists(\?[^<>"\']*)?$#', $back) ? $back : '/coordinator/lists';
        redirect(str_contains($back, '?') ? $back . '&success=1' : $back . '?success=1');
    }
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Termin bearbeiten', 'lists', function() use ($error, $event) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/event_form.php';
});
