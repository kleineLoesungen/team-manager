<?php
// src/coach/list_settings_handler.php — GET/POST /coach/lists/{id}/settings (LIST-05)

declare(strict_types=1);

require_coach();

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();
$error   = '';

// Verify list belongs to this team
$stmt = $pdo->prepare("SELECT id, name, visibility FROM lists WHERE id = ? AND team_id = ?");
$stmt->execute([$list_id, $_SESSION['team_id']]);
$list = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$list) {
    http_response_code(404);
    echo '<h1>Liste nicht gefunden</h1>';
    exit;
}

require ROOT_PATH . '/src/templates/coach/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $new_visibility = $_POST['visibility'] ?? '';

    if (!in_array($new_visibility, ['public', 'protected', 'private'])) {
        $error = 'Ungültiger Sichtbarkeits-Status.';
    } else {
        try {
            $upd = $pdo->prepare(
                "UPDATE lists SET visibility = ?, updated_at = NOW()
                 WHERE id = ? AND team_id = ?"
            );
            $upd->execute([$new_visibility, $list_id, $_SESSION['team_id']]);
            redirect('/coach/lists/' . $list_id . '?success=1');
        } catch (PDOException $e) {
            error_log('List settings error: ' . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten.';
        }
    }
}

render_coach_page('Listen-Einstellungen', 'lists', function() use ($list, $error) {
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title"><?= e($list['name']) ?></h5>
            <form method="POST" action="/coach/lists/<?= (int)$list['id'] ?>/settings">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Sichtbarkeit</label>
                    <select name="visibility" class="form-select">
                        <option value="public"    <?= $list['visibility'] === 'public'    ? 'selected' : '' ?>>
                            Öffentlich (public) — Spieler können eigene Zeile bearbeiten
                        </option>
                        <option value="protected" <?= $list['visibility'] === 'protected' ? 'selected' : '' ?>>
                            Geschützt (protected) — Nur Trainer schreibt, Spieler sehen nichts
                        </option>
                        <option value="private"   <?= $list['visibility'] === 'private'   ? 'selected' : '' ?>>
                            Privat (private) — Nur Trainer sieht und schreibt
                        </option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
                <a href="/coach/lists/<?= (int)$list['id'] ?>" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
            </form>
        </div>
    </div>
    <?php
});
