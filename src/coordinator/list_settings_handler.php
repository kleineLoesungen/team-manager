<?php
// src/coach/list_settings_handler.php — GET/POST /coordinator/lists/{id}/settings (LIST-05)

declare(strict_types=1);

require_coordinator();
require_once ROOT_PATH . '/src/db/list_auto_visibility.php';

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();
$error   = '';

// Fetch list including show_all_rows, is_hidden, date, description, and optional time columns
$time_cols = ', time_start, time_end, auto_visibility, auto_visibility_hours, auto_visibility_done_at, list_type, calendar_column_id';
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
require_once ROOT_PATH . '/src/db/resources.php';
require_once ROOT_PATH . '/src/db/calendar.php';

// Persönlicher Kalender (Issue #12): wählbare Ja/Nein-Spalten dieser Mitgliederliste
$calendar_columns = ($list['list_type'] ?? 'member') === 'member'
    ? list_calendar_columns($pdo, $list_id, (int)$_SESSION['team_id']) : [];
$resources = resources_active($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (isset($_POST['action']) && $_POST['action'] === 'bind_column' && ($list['list_type'] ?? 'member') === 'free') {
        // Freie Listen haben keine Mitgliederzeilen: globale Spalten (Statistik pro Mitglied) passen nicht
        $error = 'Freie Listen haben keine globalen Spalten.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'bind_column') {
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
                    // War sie die Kalender-Spalte, erscheint die Liste wieder immer
                    $pdo->prepare("UPDATE lists SET calendar_column_id = NULL WHERE id = ? AND calendar_column_id = ?")
                        ->execute([$list_id, $col_id]);
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
        $new_description = mb_substr(trim($_POST['description'] ?? ''), 0, 500);
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

        // Automatische Umstellung der Sichtbarkeit ('' = aus)
        $new_auto       = $_POST['auto_visibility'] ?? '';
        $raw_auto_hours = trim($_POST['auto_visibility_hours'] ?? '');
        $new_auto_hours = $raw_auto_hours === '' ? 0 : filter_var($raw_auto_hours, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => LIST_AUTO_VISIBILITY_MAX_HOURS]]);

        // Kalender-Spalte: nur eine der Ja/Nein-Spalten dieser Liste, sonst „immer“
        $raw_cal_col  = (int)($_POST['calendar_column'] ?? 0);
        $new_cal_col  = isset($calendar_columns[$raw_cal_col]) ? $raw_cal_col : null;

        if ($new_name === '') {
            $error = 'Name ist erforderlich.';
        } elseif (mb_strlen($new_name) > 100) {
            $error = 'Name darf max. 100 Zeichen haben.';
        } elseif (!in_array($new_visibility, ['public', 'protected', 'private'])) {
            $error = 'Ungültiger Sichtbarkeits-Status.';
        } elseif (!in_array($new_auto, ['', 'public', 'protected', 'private'], true)) {
            $error = 'Ungültige automatische Sichtbarkeit.';
        } elseif ($new_auto !== '' && $new_auto_hours === false) {
            $error = 'Stunden vor Beginn: Gib eine ganze Zahl von 0 bis ' . LIST_AUTO_VISIBILITY_MAX_HOURS . ' ein.';
        } elseif ($new_auto !== '' && $new_date === '') {
            $error = 'Für die automatische Umstellung braucht die Liste ein Datum.';
        } else {
            try {
                $upd = $pdo->prepare(
                    "UPDATE lists SET name = ?, visibility = ?, show_all_rows = ?, is_hidden = ?,
                            description = ?, date = ?, location = ?, time_start = ?, time_end = ?,
                            -- Regel wird wieder scharf, sobald sie selbst, Datum oder Beginn sich ändern
                            -- (SET-Ausdrücke sehen die alten Werte der Zeile)
                            auto_visibility_done_at = CASE
                                WHEN auto_visibility IS NOT DISTINCT FROM ?::varchar
                                 AND auto_visibility_hours = ?::int
                                 AND date IS NOT DISTINCT FROM ?::date
                                 AND time_start IS NOT DISTINCT FROM ?::time
                                THEN auto_visibility_done_at ELSE NULL END,
                            -- Erinnerung ebenso: neu fällig, sobald Regel, Datum oder Beginn sich ändern
                            auto_reminder_sent_at = CASE
                                WHEN auto_visibility IS NOT DISTINCT FROM ?::varchar
                                 AND auto_visibility_hours = ?::int
                                 AND date IS NOT DISTINCT FROM ?::date
                                 AND time_start IS NOT DISTINCT FROM ?::time
                                THEN auto_reminder_sent_at ELSE NULL END,
                            auto_visibility = ?, auto_visibility_hours = ?,
                            calendar_column_id = ?,
                            updated_at = NOW()
                     WHERE id = ? AND team_id = ?"
                );
                $auto_val  = $new_auto !== '' ? $new_auto : null;
                $hours_val = $new_auto !== '' ? (int)$new_auto_hours : 0;
                $date_val  = $new_date !== '' ? $new_date : null;
                $ts_val    = $new_time_start !== '' ? $new_time_start : null;
                $upd->execute([
                    $new_name, $new_visibility, $new_show_all_rows, $new_is_hidden,
                    $new_description !== '' ? $new_description : null,
                    $date_val,
                    $new_location !== '' ? $new_location : null,
                    $ts_val,
                    $new_time_end   !== '' ? $new_time_end   : null,
                    $auto_val, $hours_val, $date_val, $ts_val,
                    $auto_val, $hours_val, $date_val, $ts_val,
                    $auto_val, $hours_val,
                    $new_cal_col,
                    $list_id, $_SESSION['team_id'],
                ]);
                resources_save($pdo, (int)$_SESSION['team_id'], 'list', $list_id, resources_from_post());
                // Kurzfristige Änderung per Push, falls gewünscht (src/push/auto_push.php)
                $pushed = change_push_after_save($pdo, (int)$_SESSION['team_id'], 'list', $list_id, $new_name, $new_visibility,
                    $list, ['date' => $date_val, 'time_start' => $ts_val,
                            'time_end' => $new_time_end !== '' ? $new_time_end : null,
                            'location' => $new_location !== '' ? $new_location : null]);
                redirect('/coordinator/lists/' . $list_id . '?success=1'
                         . ($pushed ? '&notify_success=' . urlencode('Änderung per Push gemeldet.') : ''));
            } catch (PDOException $e) {
                error_log('List settings error: ' . $e->getMessage());
                $error = 'Ein Fehler ist aufgetreten.';
            }
        }
    }
}

$resource_selected = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '')
    ? resources_from_post() : resources_booked_ids($pdo, 'list', $list_id);
$resource_conflicts = $resource_selected ? resources_conflicts($pdo, 'list', $list_id) : [];

render_coach_page('Listen-Einstellungen', 'contents', function() use ($list, $error, $local_columns, $delete_pending_col_id, $global_columns, $unbind_pending_col_id, $available_columns, $resources, $resource_selected, $resource_conflicts, $calendar_columns) {
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
                    <label for="list_description" class="form-label fw-semibold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="list_description" name="description" class="form-control" rows="2" maxlength="500"><?= e($list['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-4">
                    <label for="list_date" class="form-label fw-semibold">Datum <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" id="list_date" name="date" class="form-control"
                           value="<?= e($list['date'] ?? '') ?>">
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
                </div>
                <?php render_resource_picker($resources, $resource_selected, true, 'list:' . (int)$list['id'], $resource_conflicts); ?>
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
                    <label for="auto_visibility" class="form-label fw-semibold">Sichtbarkeit automatisch umstellen <span class="text-muted fw-normal">(optional)</span></label>
                    <select id="auto_visibility" name="auto_visibility" class="form-select mb-2">
                        <option value="" <?= empty($list['auto_visibility']) ? 'selected' : '' ?>>Nicht automatisch</option>
                        <?php foreach (['public', 'protected', 'private'] as $v): ?>
                        <option value="<?= $v ?>" <?= ($list['auto_visibility'] ?? '') === $v ? 'selected' : '' ?>>auf <?= e(list_visibility_label($v)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="d-flex align-items-center gap-2">
                        <input type="number" id="auto_visibility_hours" name="auto_visibility_hours"
                               class="form-control tm-input-hours" inputmode="numeric"
                               min="0" max="<?= LIST_AUTO_VISIBILITY_MAX_HOURS ?>" step="1"
                               value="<?= (int)($list['auto_visibility_hours'] ?? 0) ?>">
                        <label for="auto_visibility_hours" class="mb-0">Std. vor Beginn</label>
                    </div>
                    <div class="form-text">
                        Zum Beispiel „auf Geschützt, 2 Std. vor Beginn“ als Anmeldeschluss oder „auf Öffentlich,
                        48 Std. vor Beginn“ zum Freischalten. Ohne Uhrzeit zählt 00:00 als Beginn.
                    </div>
                    <?php render_auto_visibility_hint($list); ?>
                    <?php render_auto_reminder_hint(); ?>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Anzeige</label>
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="show_all_rows" id="show_all_rows" value="1"
                               <?= $list['show_all_rows'] ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0" for="show_all_rows">Mitglieder sehen Einträge anderer Mitglieder</label>
                    </div>
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="is_hidden" id="is_hidden" value="1"
                               <?= $list['is_hidden'] ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0" for="is_hidden">Liste verstecken</label>
                    </div>
                    <div class="form-text mt-0">Versteckte Listen erscheinen eingeklappt am Ende der Übersicht.</div>
                </div>
                <?php if (($list['list_type'] ?? 'member') === 'member'):
                    $cal_selected = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '')
                        ? (string)($_POST['calendar_column'] ?? '') : (string)($list['calendar_column_id'] ?? '');
                    render_calendar_column_select($calendar_columns, isset($calendar_columns[(int)$cal_selected]) ? $cal_selected : '');
                endif; ?>
                <?php if (change_push_window($list['date'] ?? null)) render_change_push_switch(); ?>
                <button type="submit" class="btn btn-primary min-touch">Liste speichern</button>
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
                <li class="list-group-item d-flex justify-content-between align-items-center">
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
    <?php if (($list['list_type'] ?? 'member') !== 'free' && (!empty($global_columns) || !empty($available_columns))): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h6 class="card-title">Globale Spalten</h6>
            <p class="text-muted small mb-3">Globale Spalten stammen aus der Team- oder Systemkonfiguration. Entfernen trennt die Spalte von dieser Liste und löscht alle zugehörigen Einträge — die Spalte selbst bleibt erhalten.</p>
            <?php if (!empty($global_columns)): ?>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($global_columns as $col): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
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
