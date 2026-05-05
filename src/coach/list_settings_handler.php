<?php
// src/coach/list_settings_handler.php — GET/POST /moderator/lists/{id}/settings (LIST-05)

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

// Fetch local columns for this list (list_id IS NOT NULL = local columns only)
$local_cols_stmt = $pdo->prepare(
    "SELECT id, name, data_type FROM columns
     WHERE list_id = ? AND team_id = ? AND is_active = TRUE
     ORDER BY sort_order, created_at"
);
$local_cols_stmt->execute([$list_id, $_SESSION['team_id']]);
$local_columns = $local_cols_stmt->fetchAll(PDO::FETCH_ASSOC);

$delete_pending_col_id = null;

// Fetch global columns attached to this list via junction table
$global_cols_stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.data_type
     FROM columns c
     JOIN list_global_columns lgc ON lgc.column_id = c.id
     WHERE lgc.list_id = ? AND c.team_id = ? AND c.is_active = TRUE
     ORDER BY c.sort_order, c.created_at"
);
$global_cols_stmt->execute([$list_id, $_SESSION['team_id']]);
$global_columns = $global_cols_stmt->fetchAll(PDO::FETCH_ASSOC);

$unbind_pending_col_id = null;

require ROOT_PATH . '/src/templates/coach/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Handle column deletion (two-step: first POST shows confirm, second POST executes)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_column') {
        $col_id  = (int)($_POST['column_id'] ?? 0);
        $confirm = (int)($_POST['confirm']   ?? 0);

        if ($confirm !== 1) {
            // Show confirmation step — store pending col_id and fall through to render
            $delete_pending_col_id = $col_id;
        } else {
            // Execute deletion — triple ownership check (id + list_id + team_id)
            try {
                $del = $pdo->prepare(
                    "DELETE FROM columns WHERE id = ? AND list_id = ? AND team_id = ?"
                );
                $del->execute([$col_id, $list_id, $_SESSION['team_id']]);
                // Cells cascade-delete automatically via FK (cells.column_id REFERENCES columns ON DELETE CASCADE)
                redirect('/moderator/lists/' . $list_id . '/settings?success=1');
            } catch (PDOException $e) {
                error_log('Column delete error: ' . $e->getMessage());
                $error = 'Fehler beim Löschen der Spalte.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'unbind_column') {
        $col_id  = (int)($_POST['column_id'] ?? 0);
        $confirm = (int)($_POST['confirm']   ?? 0);

        if ($confirm !== 1) {
            // Show confirmation step — store pending col_id and fall through to render
            $unbind_pending_col_id = $col_id;
        } else {
            // Ownership check: junction row must exist AND column belongs to this team
            $check = $pdo->prepare(
                "SELECT 1 FROM list_global_columns lgc
                 JOIN columns c ON c.id = lgc.column_id
                 WHERE lgc.list_id = ? AND lgc.column_id = ? AND c.team_id = ?"
            );
            $check->execute([$list_id, $col_id, $_SESSION['team_id']]);
            if (!$check->fetch()) {
                $error = 'Spalte nicht gefunden.';
            } else {
                try {
                    $pdo->beginTransaction();
                    // Delete cells for this column in this list
                    $del_cells = $pdo->prepare(
                        "DELETE FROM cells WHERE list_id = ? AND column_id = ?"
                    );
                    $del_cells->execute([$list_id, $col_id]);
                    // Remove junction row (column itself stays untouched)
                    $del_lgc = $pdo->prepare(
                        "DELETE FROM list_global_columns WHERE list_id = ? AND column_id = ?"
                    );
                    $del_lgc->execute([$list_id, $col_id]);
                    $pdo->commit();
                    redirect('/moderator/lists/' . $list_id . '/settings?success=1');
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log('Unbind column error: ' . $e->getMessage());
                    $error = 'Fehler beim Entfernen der Spalte.';
                }
            }
        }
    } else {
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
                redirect('/moderator/lists/' . $list_id . '?success=1');
            } catch (PDOException $e) {
                error_log('List settings error: ' . $e->getMessage());
                $error = 'Ein Fehler ist aufgetreten.';
            }
        }
    }
}

render_coach_page('Listen-Einstellungen', 'lists', function() use ($list, $error, $local_columns, $delete_pending_col_id, $global_columns, $unbind_pending_col_id) {
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title"><?= e($list['name']) ?></h5>
            <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/settings">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Sichtbarkeit</label>
                    <select name="visibility" class="form-select">
                        <option value="public"    <?= $list['visibility'] === 'public'    ? 'selected' : '' ?>>
                            Öffentlich — Mitglieder bearbeiten eigene Zeile
                        </option>
                        <option value="protected" <?= $list['visibility'] === 'protected' ? 'selected' : '' ?>>
                            Geschützt — Mitglieder sehen eigene Zeile (nur lesen)
                        </option>
                        <option value="private"   <?= $list['visibility'] === 'private'   ? 'selected' : '' ?>>
                            Privat — Nur Moderator sieht und bearbeitet
                        </option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Zeilen anderer Mitglieder</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_all_rows"
                               id="show_all_rows" value="1"
                               <?= $list['show_all_rows'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_all_rows">
                            Mitglieder sehen Einträge anderer Mitglieder
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
                <a href="/moderator/lists/<?= (int)$list['id'] ?>" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
            </form>
        </div>
    </div>
    <?php if (!empty($local_columns)): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h6 class="card-title">Lokale Spalten</h6>
            <p class="text-muted small mb-3">Lokale Spalten gehören nur zu dieser Liste. Löschen entfernt auch alle zugehörigen Einträge.</p>
            <ul class="list-group list-group-flush">
                <?php foreach ($local_columns as $col): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                        <span class="fw-medium"><?= e($col['name']) ?></span>
                        <span class="badge bg-light text-dark border ms-2 small">
                            <?= match($col['data_type']) { 'boolean' => 'Ja/Nein', 'number' => 'Zahl', 'text' => 'Text', default => e($col['data_type']) } ?>
                        </span>
                    </div>
                    <?php if ($delete_pending_col_id !== null && $delete_pending_col_id === (int)$col['id']): ?>
                        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/settings" class="d-flex gap-2 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_column">
                            <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
                            <input type="hidden" name="confirm" value="1">
                            <span class="text-danger small me-2">Spalte und alle Einträge löschen?</span>
                            <button type="submit" class="btn btn-sm btn-danger min-touch">Ja, löschen</button>
                            <a href="/moderator/lists/<?= (int)$list['id'] ?>/settings" class="btn btn-sm btn-outline-secondary min-touch">Abbrechen</a>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/settings">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_column">
                            <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
                            <input type="hidden" name="confirm" value="0">
                            <button type="submit" class="btn btn-sm btn-outline-danger min-touch">Löschen</button>
                        </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($global_columns)): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h6 class="card-title">Globale Spalten</h6>
            <p class="text-muted small mb-3">Globale Spalten stammen aus der Team-Konfiguration. Entfernen trennt die Spalte von dieser Liste und löscht alle zugehörigen Einträge — die Spalte selbst bleibt erhalten.</p>
            <ul class="list-group list-group-flush">
                <?php foreach ($global_columns as $col): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                        <span class="fw-medium"><?= e($col['name']) ?></span>
                        <span class="badge bg-light text-dark border ms-2 small">
                            <?= match($col['data_type']) { 'boolean' => 'Ja/Nein', 'number' => 'Zahl', 'text' => 'Text', default => e($col['data_type']) } ?>
                        </span>
                    </div>
                    <?php if ($unbind_pending_col_id !== null && $unbind_pending_col_id === (int)$col['id']): ?>
                        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/settings" class="d-flex gap-2 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unbind_column">
                            <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
                            <input type="hidden" name="confirm" value="1">
                            <span class="text-danger small me-2">Spalte aus dieser Liste entfernen? Alle Einträge dieser Spalte werden ebenfalls gelöscht.</span>
                            <button type="submit" class="btn btn-sm btn-danger min-touch">Ja, entfernen</button>
                            <a href="/moderator/lists/<?= (int)$list['id'] ?>/settings" class="btn btn-sm btn-outline-secondary min-touch">Abbrechen</a>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/settings">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unbind_column">
                            <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
                            <input type="hidden" name="confirm" value="0">
                            <button type="submit" class="btn btn-sm btn-outline-danger min-touch">Entfernen</button>
                        </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    <div class="card border-danger mt-4">
        <div class="card-body">
            <h6 class="card-title text-danger">Gefahrenzone</h6>
            <p class="text-muted small mb-3">Diese Liste und alle enthaltenen Daten werden unwiderruflich gelöscht.</p>
            <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/delete">
                <?= csrf_field() ?>
                <input type="hidden" name="confirm" value="0">
                <button type="submit" class="btn btn-outline-danger min-touch">Liste löschen</button>
            </form>
        </div>
    </div>
    <?php
});
