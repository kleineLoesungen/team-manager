<?php
// src/coordinator/event_delete_handler.php — POST /coordinator/events/{id}/delete
// Zwei Schritte wie bei Listen: erst Bestätigungsseite, dann (confirm=1) löschen.
// Die Ressourcen-Belegung verschwindet mit dem Termin (ON DELETE CASCADE).

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/coordinator/lists');

require_csrf();

$event_id = (int)($_REQUEST['event_id'] ?? 0);
$pdo      = get_db();
$team_id  = (int)$_SESSION['team_id'];

$stmt = $pdo->prepare("SELECT id, title, date FROM events WHERE id = ? AND team_id = ?");
$stmt->execute([$event_id, $team_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) redirect('/coordinator/lists');

$back = $_POST['_back'] ?? '';
$back = preg_match('#^/coordinator/lists(\?[^<>"\']*)?$#', $back) ? $back : '/coordinator/lists';

if ((int)($_POST['confirm'] ?? 0) !== 1) {
    require ROOT_PATH . '/src/templates/coordinator/layout.php';
    render_coach_page('Termin löschen', 'lists', function () use ($event, $back) { ?>
        <div class="alert alert-danger">
            Termin <strong><?= e($event['title']) ?></strong> vom <?= e(date('d.m.Y', strtotime($event['date']))) ?> wirklich löschen?
            Das lässt sich nicht rückgängig machen.
        </div>
        <form method="POST" action="/coordinator/events/<?= (int)$event['id'] ?>/delete">
            <?= csrf_field() ?>
            <input type="hidden" name="confirm" value="1">
            <input type="hidden" name="_back" value="<?= e($back) ?>">
            <button type="submit" class="btn btn-danger min-touch">Termin endgültig löschen</button>
            <a href="/coordinator/events/<?= (int)$event['id'] ?>/edit" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
        </form>
    <?php });
    return;
}

$pdo->prepare("DELETE FROM events WHERE id = ? AND team_id = ?")->execute([$event_id, $team_id]);
redirect($back . (str_contains($back, '?') ? '&' : '?') . 'deleted=1');
