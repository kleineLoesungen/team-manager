<?php
// src/coach/list_settings_handler.php — GET/POST /coach/lists/{id}/settings (LIST-05)

declare(strict_types=1);

require_coach();

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();
$error   = '';

// Fetch list including show_all_rows, is_hidden, date, and description
$stmt = $pdo->prepare("SELECT id, name, visibility, show_all_rows, is_hidden, date, description FROM lists WHERE id = ? AND team_id = ?");
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
    $new_is_hidden     = isset($_POST['is_hidden'])     ? 1 : 0;
    $new_date          = trim($_POST['date'] ?? '');
    $new_description   = trim($_POST['description'] ?? '');
    if ($new_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
        $new_date = '';
    }

    if (!in_array($new_visibility, ['public', 'protected', 'private'])) {
        $error = 'Ungültiger Sichtbarkeits-Status.';
    } else {
        try {
            $upd = $pdo->prepare(
                "UPDATE lists SET visibility = ?, show_all_rows = ?, is_hidden = ?, date = ?, description = ?, updated_at = NOW()
                 WHERE id = ? AND team_id = ?"
            );
            $upd->execute([
                $new_visibility,
                $new_show_all_rows,
                $new_is_hidden,
                $new_date !== '' ? $new_date : null,
                $new_description !== '' ? $new_description : null,
                $list_id,
                $_SESSION['team_id'],
            ]);
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
                <div class="mb-4">
                    <label class="form-label fw-semibold">Sichtbarkeit in der Übersicht</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_hidden"
                               id="is_hidden" value="1"
                               <?= $list['is_hidden'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_hidden">
                            Liste verstecken (erscheint eingeklappt am Ende der Übersicht)
                        </label>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="list_date" class="form-label fw-semibold">Datum <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" id="list_date" name="date" class="form-control"
                           value="<?= e($list['date'] ?? '') ?>">
                    <div class="form-text">z. B. Datum des Spiels oder Trainings</div>
                </div>
                <div class="mb-4">
                    <label for="list_desc" class="form-label fw-semibold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="list_desc" name="description" class="form-control" rows="2" maxlength="500"
                              placeholder="z. B. Heimspiel gegen FC Muster, Pokalrunde 2"><?= e($list['description'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
                <a href="/coach/lists/<?= (int)$list['id'] ?>" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
            </form>
        </div>
    </div>
    <div class="card border-danger mt-4">
        <div class="card-body">
            <h6 class="card-title text-danger">Gefahrenzone</h6>
            <p class="text-muted small mb-3">Diese Liste und alle enthaltenen Daten werden unwiderruflich gelöscht.</p>
            <form method="POST" action="/coach/lists/<?= (int)$list['id'] ?>/delete">
                <?= csrf_field() ?>
                <input type="hidden" name="confirm" value="0">
                <button type="submit" class="btn btn-outline-danger min-touch">Liste löschen</button>
            </form>
        </div>
    </div>
    <?php
});
