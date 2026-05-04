<?php
// src/coach/list_delete_handler.php — POST /moderator/lists/{id}/delete (LIST-DELETE)

declare(strict_types=1);

require_coach();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/moderator/lists');
}

require_csrf();

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$confirm = (int)($_POST['confirm'] ?? 0);
$pdo     = get_db();

// Ownership check — only this team's lists are accessible
$stmt = $pdo->prepare("SELECT id, name FROM lists WHERE id = ? AND team_id = ?");
$stmt->execute([$list_id, $_SESSION['team_id']]);
$list = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$list) {
    redirect('/moderator/lists');
}

require ROOT_PATH . '/src/templates/coach/layout.php';

if ($confirm !== 1) {
    // Step 1: Show confirmation page — no delete yet
    render_coach_page('Liste löschen', 'lists', function() use ($list) {
        ?>
        <div class="alert alert-danger">
            Liste <strong><?= e($list['name']) ?></strong> wirklich löschen?
            Alle Daten (Spalten, Einträge) werden <strong>unwiderruflich</strong> gelöscht.
        </div>
        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/delete">
            <?= csrf_field() ?>
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn btn-danger min-touch">Endgültig löschen</button>
            <a href="/moderator/lists/<?= (int)$list['id'] ?>/settings" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
        </form>
        <?php
    });
    return;
}

// Step 2: Execute delete — DB cascades remove columns, cells, list_global_columns
try {
    $del = $pdo->prepare("DELETE FROM lists WHERE id = ? AND team_id = ?");
    $del->execute([$list_id, $_SESSION['team_id']]);
    redirect('/moderator/lists');
} catch (PDOException $e) {
    error_log('List delete error: ' . $e->getMessage());
    render_coach_page('Liste löschen', 'lists', function() {
        ?>
        <div class="alert alert-danger">Ein Fehler ist aufgetreten. Die Liste konnte nicht gelöscht werden.</div>
        <?php
    });
}
