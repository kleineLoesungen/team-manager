<?php
// src/coordinator/file_delete_handler.php — POST /coordinator/files/{id}/delete

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/coordinator/lists');
}

require_csrf();

$file_id = (int)($_REQUEST['file_id'] ?? 0);
$confirm = (int)($_POST['confirm'] ?? 0);
$pdo     = get_db();

$stmt = $pdo->prepare("SELECT id, name FROM files WHERE id = ? AND team_id = ?");
$stmt->execute([$file_id, $_SESSION['team_id']]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    redirect('/coordinator/lists');
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

if ($confirm !== 1) {
    render_coach_page('Datei löschen', 'lists', function() use ($file) {
        ?>
        <div class="alert alert-danger">
            Datei <strong><?= e($file['name']) ?></strong> wirklich löschen?
            Der gesamte Inhalt wird <strong>unwiderruflich</strong> gelöscht.
        </div>
        <form method="POST" action="/coordinator/files/<?= (int)$file['id'] ?>/delete">
            <?= csrf_field() ?>
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn btn-danger min-touch">Endgültig löschen</button>
            <a href="/coordinator/files/<?= (int)$file['id'] ?>" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
        </form>
        <?php
    });
    return;
}

try {
    $del = $pdo->prepare("DELETE FROM files WHERE id = ? AND team_id = ?");
    $del->execute([$file_id, $_SESSION['team_id']]);
    redirect('/coordinator/lists');
} catch (PDOException $e) {
    error_log('File delete error: ' . $e->getMessage());
    render_coach_page('Datei löschen', 'lists', function() {
        echo '<div class="alert alert-danger">Ein Fehler ist aufgetreten. Die Datei konnte nicht gelöscht werden.</div>';
    });
}
