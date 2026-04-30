<?php
// src/coach/list_settings_handler.php — GET/POST /coach/lists/{id}/settings (LIST-05)

declare(strict_types=1);

require_coach();

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();
$error   = '';

// Fetch list including show_all_rows
$stmt = $pdo->prepare("SELECT id, name, visibility, show_all_rows FROM lists WHERE id = ? AND team_id = ?");
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

    $new_visibility    = $_POST['visibility'] ?? '';
    $new_show_all_rows = isset($_POST['show_all_rows']) ? 1 : 0;

    if (!in_array($new_visibility, ['public', 'protected', 'private'])) {
        $error = 'Ungültiger Sichtbarkeits-Status.';
    } else {
        try {
            $upd = $pdo->prepare(
                "UPDATE lists SET visibility = ?, show_all_rows = ?, updated_at = NOW()
                 WHERE id = ? AND team_id = ?"
            );
            $upd->execute([$new_visibility, $new_show_all_rows, $list_id, $_SESSION['team_id']]);
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
                <div class="mb-4">
                    <label class="form-label fw-semibold">Sichtbarkeit</label>
                    <select name="visibility" class="form-select">
                        <option value="public"    <?= $list['visibility'] === 'public'    ? 'selected' : '' ?>>
                            Öffentlich — Spieler bearbeiten eigene Zeile
                        </option>
                        <option value="protected" <?= $list['visibility'] === 'protected' ? 'selected' : '' ?>>
                            Geschützt — Spieler sehen eigene Zeile (nur lesen)
                        </option>
                        <option value="private"   <?= $list['visibility'] === 'private'   ? 'selected' : '' ?>>
                            Privat — Nur Trainer sieht und bearbeitet
                        </option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Zeilen anderer Spieler</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_all_rows"
                               id="show_all_rows" value="1"
                               <?= $list['show_all_rows'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_all_rows">
                            Spieler sehen Einträge anderer Spieler
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
                <a href="/coach/lists/<?= (int)$list['id'] ?>" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
            </form>
        </div>
    </div>
    <?php
});
