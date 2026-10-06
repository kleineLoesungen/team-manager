<?php
// src/member/event_delete_handler.php — POST /member/events/{id}/delete
// Eigene Termine; zwei Schritte wie bei Koordinatoren: Bestätigungsseite, dann (confirm=1) löschen.

declare(strict_types=1);

require_member();
require_once ROOT_PATH . '/src/db/events.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/member/lists');
require_csrf();

$event_id = (int)($_REQUEST['event_id'] ?? 0);
$pdo      = get_db();
$team_id  = (int)$_SESSION['team_id'];
$event    = event_load($pdo, $event_id, $team_id);
if (!$event || !event_member_may_edit($pdo, $event, (int)$_SESSION['user_id'])) redirect('/member/lists');

$back = event_back_url('member');

if ((int)($_POST['confirm'] ?? 0) !== 1) {
    require ROOT_PATH . '/src/templates/member/layout.php';
    render_member_page('Termin löschen', 'lists', function () use ($event, $back) { ?>
        <div class="alert alert-danger">
            Termin <strong><?= e($event['title']) ?></strong> vom <?= e(date('d.m.Y', strtotime($event['date']))) ?> wirklich löschen?
            Das lässt sich nicht rückgängig machen.
        </div>
        <form method="POST" action="/member/events/<?= (int)$event['id'] ?>/delete">
            <?= csrf_field() ?>
            <input type="hidden" name="confirm" value="1">
            <input type="hidden" name="_back" value="<?= e($back) ?>">
            <button type="submit" class="btn btn-danger min-touch">Termin endgültig löschen</button>
            <a href="/member/events/<?= (int)$event['id'] ?>/edit" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
        </form>
    <?php });
    return;
}

$pdo->prepare("DELETE FROM events WHERE id = ? AND team_id = ? AND created_by = ?")
    ->execute([$event_id, $team_id, (int)$_SESSION['user_id']]);
redirect($back . (str_contains($back, '?') ? '&' : '?') . 'deleted=1');
