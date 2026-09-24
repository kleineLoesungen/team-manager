<?php
// src/coach/list_settings_handler.php — GET/POST /coordinator/lists/{id}/settings (LIST-05)

declare(strict_types=1);

require_coordinator();

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();
$error   = '';

// Fetch list including show_all_rows, is_hidden, date, description, and optional time columns
$time_cols = ', time_start, time_end';
$stmt = $pdo->prepare("SELECT id, name, visibility, show_all_rows, is_hidden, date, description, location{$time_cols} FROM lists WHERE id = ? AND team_id = ?");
$stmt->execute([$list_id, $_SESSION['team_id']]);
$list = $stmt->fetch(PDO::FETCH_ASSOC);
if ($list) {
    // pdo_pgsql returns booleans as 't'/'f'; filter_var does not handle these — use explicit list
    $list['show_all_rows'] = in_array($list['show_all_rows'] ?? false, [true, 1, '1', 't', 'true', 'yes', 'on'], true);
    $list['is_hidden']     = in_array($list['is_hidden']     ?? false, [true, 1, '1', 't', 'true', 'yes', 'on'], true);
}

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

// Fetch global columns attached to this list via junction table (including system columns)
set_admin_context($pdo);
$global_cols_stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.data_type, c.is_system
     FROM columns c
     JOIN list_global_columns lgc ON lgc.column_id = c.id
     WHERE lgc.list_id = ? AND (c.team_id = ? OR c.is_system = TRUE) AND c.is_active = TRUE
     ORDER BY c.is_system DESC, c.sort_order, c.created_at"
);
$global_cols_stmt->execute([$list_id, $_SESSION['team_id']]);
$global_columns = $global_cols_stmt->fetchAll(PDO::FETCH_ASSOC);

$unbind_pending_col_id = null;

// Fetch global columns not yet linked to this list (available to add)
$available_stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.data_type, c.is_system
     FROM columns c
     WHERE c.list_id IS NULL AND c.is_active = TRUE
       AND (c.team_id = ? OR c.is_system = TRUE)
       AND NOT EXISTS (
           SELECT 1 FROM list_global_columns lgc WHERE lgc.list_id = ? AND lgc.column_id = c.id
       )
     ORDER BY c.is_system DESC, c.sort_order, c.name"
);
$available_stmt->execute([$_SESSION['team_id'], $list_id]);
$available_columns = $available_stmt->fetchAll(PDO::FETCH_ASSOC);

require ROOT_PATH . '/src/templates/coordinator/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (isset($_POST['action']) && $_POST['action'] === 'bind_column') {
        $col_id = (int)($_POST['column_id'] ?? 0);
        // Validate: column must belong to this team or be a system column, and not already linked
        $check = $pdo->prepare(
            "SELECT c.id FROM columns c
             WHERE c.id = ? AND c.list_id IS NULL AND c.is_active = TRUE
               AND (c.team_id = ? OR c.is_system = TRUE)
               AND NOT EXISTS (
                   SELECT 1 FROM list_global_columns lgc WHERE lgc.list_id = ? AND lgc.column_id = c.id
               )"
        );
        $check->execute([$col_id, $_SESSION['team_id'], $list_id]);
        if (!$check->fetch()) {
            $error = 'Spalte nicht gefunden oder bereits hinzugefügt.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO list_global_columns (list_id, column_id) VALUES (?, ?)")
                    ->execute([$list_id, $col_id]);

                // Pre-fill '0' for number columns for all active members
                $type_row = $pdo->prepare("SELECT data_type FROM columns WHERE id = ?");
                $type_row->execute([$col_id]);
                if ($type_row->fetchColumn() === 'number') {
                    $mids = $pdo->prepare(
                        "SELECT id FROM users WHERE team_id = ? AND role = 'member' AND is_active = TRUE"
                    );
                    $mids->execute([$_SESSION['team_id']]);
                    $cell_ins = $pdo->prepare(
                        "INSERT INTO cells (list_id, column_id, member_id, value) VALUES (?, ?, ?, '0')
                         ON CONFLICT (list_id, column_id, member_id) DO NOTHING"
                    );
                    foreach ($mids->fetchAll(PDO::FETCH_COLUMN) as $mid) {
                        $cell_ins->execute([$list_id, $col_id, (int)$mid]);
                    }
                }

                $pdo->commit();
                redirect('/coordinator/lists/' . $list_id . '/settings?success=1');
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Bind column error: ' . $e->getMessage());
                $error = 'Fehler beim Hinzufügen der Spalte.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_column') {
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
                redirect('/coordinator/lists/' . $list_id . '/settings?success=1');
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
            // Ownership check: junction row must exist AND column belongs to this team OR is a system column
            $check = $pdo->prepare(
                "SELECT 1 FROM list_global_columns lgc
                 JOIN columns c ON c.id = lgc.column_id
                 WHERE lgc.list_id = ? AND lgc.column_id = ?
                   AND (c.team_id = ? OR c.is_system = TRUE)"
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
                    redirect('/coordinator/lists/' . $list_id . '/settings?success=1');
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log('Unbind column error: ' . $e->getMessage());
                    $error = 'Fehler beim Entfernen der Spalte.';
                }
            }
        }
    } else {
        $new_name          = trim($_POST['name'] ?? '');
        $new_visibility    = $_POST['visibility'] ?? '';
        $new_show_all_rows = isset($_POST['show_all_rows']) ? 1 : 0;
        $new_is_hidden     = isset($_POST['is_hidden'])     ? 1 : 0;
        $new_date          = trim($_POST['date'] ?? '');
        if ($new_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
            $new_date = '';
        }
        $new_location = trim($_POST['location'] ?? '');
        if (mb_strlen($new_location) > 255) {
            $new_location = mb_substr($new_location, 0, 255);
        }

        $new_time_start = '';
        $new_time_end   = '';
        $raw_ts = trim($_POST['time_start'] ?? '');
        if (preg_match('/^\d{2}:\d{2}$/', $raw_ts)) {
            $new_time_start = $raw_ts . ':00';
        }
        $raw_te = trim($_POST['time_end'] ?? '');
        if (preg_match('/^\d{2}:\d{2}$/', $raw_te)) {
            $new_time_end = $raw_te . ':00';
        }

        if ($new_name === '') {
            $error = 'Name ist erforderlich.';
        } elseif (mb_strlen($new_name) > 100) {
            $error = 'Name darf max. 100 Zeichen haben.';
        } elseif (!in_array($new_visibility, ['public', 'protected', 'private'])) {
            $error = 'Ungültiger Sichtbarkeits-Status.';
        } else {
            try {
                $upd = $pdo->prepare(
                    "UPDATE lists SET name = ?, visibility = ?, show_all_rows = ?, is_hidden = ?,
                            date = ?, location = ?, time_start = ?, time_end = ?, updated_at = NOW()
                     WHERE id = ? AND team_id = ?"
                );
                $upd->execute([
                    $new_name, $new_visibility, $new_show_all_rows, $new_is_hidden,
                    $new_date !== '' ? $new_date : null,
                    $new_location !== '' ? $new_location : null,
                    $new_time_start !== '' ? $new_time_start : null,
                    $new_time_end   !== '' ? $new_time_end   : null,
                    $list_id, $_SESSION['team_id'],
                ]);
                redirect('/coordinator/lists/' . $list_id . '?success=1');
            } catch (PDOException $e) {
                error_log('List settings error: ' . $e->getMessage());
                $error = 'Ein Fehler ist aufgetreten.';
            }
        }
    }
}

render_coach_page('Listen-Einstellungen', 'lists', function() use ($list, $error, $local_columns, $delete_pending_col_id, $global_columns, $unbind_pending_col_id, $available_columns) {
    ?>
    <div class="mb-3">
        <a href="/coordinator/lists/<?= (int)$list['id'] ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Zurück zur Liste
        </a>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if (!empty($_GET['success'])): ?><div class="alert alert-success">Gespeichert.</div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title"><?= e($list['name']) ?></h5>
            <form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>/settings">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label for="list_name" class="form-label fw-semibold">Name</label>
                    <input type="text" id="list_name" name="name"
                           class="form-control" maxlength="100" required
                           value="<?= e($list['name']) ?>">
                </div>
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
                            Privat — Nur Koordinator sieht und bearbeitet
                        </option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Zeilen anderer Mitglieder</label>
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               style="width:3em;height:1.75em;cursor:pointer;"
                               name="show_all_rows" id="show_all_rows" value="1"
                               <?= $list['show_all_rows'] ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0" for="show_all_rows">
                            Mitglieder sehen Einträge anderer Mitglieder
                        </label>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Sichtbarkeit in der Übersicht</label>
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               style="width:3em;height:1.75em;cursor:pointer;"
                               name="is_hidden" id="is_hidden" value="1"
                               <?= $list['is_hidden'] ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0" for="is_hidden">
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
                    <label class="form-label fw-semibold">Uhrzeit <span class="text-muted fw-normal">(optional)</span></label>
                    <div class="d-flex align-items-center gap-2">
                        <div>
                            <label for="list_time_start" class="form-label small text-muted mb-1">Beginn</label>
                            <input type="time" id="list_time_start" name="time_start" class="form-control"
                                   value="<?= e(isset($list['time_start']) ? substr((string)$list['time_start'], 0, 5) : '') ?>">
                        </div>
                        <div class="pt-3 text-muted">–</div>
                        <div>
                            <label for="list_time_end" class="form-label small text-muted mb-1">Ende</label>
                            <input type="time" id="list_time_end" name="time_end" class="form-control"
                                   value="<?= e(isset($list['time_end']) ? substr((string)$list['time_end'], 0, 5) : '') ?>">
                        </div>
                    </div>
                    <div class="form-text">Ohne Ende: Kalender zeigt 1 Stunde Dauer an.</div>
                </div>
                <div class="mb-4">
                    <label for="list_location" class="form-label fw-semibold">Ort <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" id="list_location" name="location"
                           class="form-control" maxlength="255"
                           value="<?= e($list['location'] ?? '') ?>">
                    <div class="form-text">z. B. Sportplatz Mitte, Turnhalle Schule</div>
                </div>
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
                <a href="/coordinator/lists/<?= (int)$list['id'] ?>" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
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
                        <form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>/settings" class="d-flex gap-2 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_column">
                            <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
                            <input type="hidden" name="confirm" value="1">
                            <span class="text-danger small me-2">Spalte und alle Einträge löschen?</span>
                            <button type="submit" class="btn btn-sm btn-danger min-touch">Ja, löschen</button>
                            <a href="/coordinator/lists/<?= (int)$list['id'] ?>/settings" class="btn btn-sm btn-outline-secondary min-touch">Abbrechen</a>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>/settings">
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
    <?php if (!empty($global_columns) || !empty($available_columns)): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h6 class="card-title">Globale Spalten</h6>
            <p class="text-muted small mb-3">Globale Spalten stammen aus der Team- oder Systemkonfiguration. Entfernen trennt die Spalte von dieser Liste und löscht alle zugehörigen Einträge — die Spalte selbst bleibt erhalten.</p>
            <?php if (!empty($global_columns)): ?>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($global_columns as $col): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                        <span class="fw-medium"><?= e($col['name']) ?></span>
                        <span class="badge bg-light text-dark border ms-2 small">
                            <?= match($col['data_type']) { 'boolean' => 'Ja/Nein', 'number' => 'Zahl', 'text' => 'Text', default => e($col['data_type']) } ?>
                        </span>
                        <?php if (!empty($col['is_system'])): ?>
                        <span class="badge bg-secondary-subtle text-secondary ms-1 small">
                            <i class="bi bi-lock me-1"></i>System
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($unbind_pending_col_id !== null && $unbind_pending_col_id === (int)$col['id']): ?>
                        <form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>/settings" class="d-flex gap-2 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unbind_column">
                            <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
                            <input type="hidden" name="confirm" value="1">
                            <span class="text-danger small me-2">Einträge dieser Spalte löschen und entfernen?</span>
                            <button type="submit" class="btn btn-sm btn-danger min-touch">Ja, entfernen</button>
                            <a href="/coordinator/lists/<?= (int)$list['id'] ?>/settings" class="btn btn-sm btn-outline-secondary min-touch">Abbrechen</a>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>/settings">
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
            <?php endif; ?>
            <?php if (!empty($available_columns)): ?>
            <form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>/settings" class="d-flex gap-2 align-items-center flex-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bind_column">
                <select name="column_id" class="form-select form-select-sm" style="max-width: 260px;">
                    <?php foreach ($available_columns as $col): ?>
                    <option value="<?= (int)$col['id'] ?>">
                        <?= e($col['name']) ?>
                        (<?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>)
                        <?= !empty($col['is_system']) ? ' · System' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary min-touch">
                    <i class="bi bi-plus me-1"></i>Hinzufügen
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="card border-danger mt-4">
        <div class="card-body">
            <h6 class="card-title text-danger">Gefahrenzone</h6>
            <p class="text-muted small mb-3">Diese Liste und alle enthaltenen Daten werden unwiderruflich gelöscht.</p>
            <form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>/delete">
                <?= csrf_field() ?>
                <input type="hidden" name="confirm" value="0">
                <button type="submit" class="btn btn-outline-danger min-touch">Liste löschen</button>
            </form>
        </div>
    </div>
    <div class="mt-4">
        <a href="/coordinator/lists/<?= (int)$list['id'] ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Zurück zur Liste
        </a>
    </div>
    <script>
    (function () {
        var KEY = 'list_settings_scroll_<?= (int)$list['id'] ?>';
        var hasPending = <?= ($delete_pending_col_id !== null || $unbind_pending_col_id !== null) ? 'true' : 'false' ?>;
        if (hasPending) {
            var saved = sessionStorage.getItem(KEY);
            if (saved !== null) {
                sessionStorage.removeItem(KEY);
                window.scrollTo(0, parseInt(saved, 10));
            }
        }
        document.querySelectorAll('input[name="confirm"][value="0"]').forEach(function (inp) {
            inp.closest('form').addEventListener('submit', function () {
                sessionStorage.setItem(KEY, window.scrollY);
            });
        });
    }());
    </script>
    <?php
});
