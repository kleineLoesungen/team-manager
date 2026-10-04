<?php
// src/templates/coordinator/list_form.php — Create list form (also creates a series of member lists)
// Variables: $error (string), $global_columns, $system_columns, $list_type (member|free), $return_to
// After a failed submit every field keeps what was entered ($old / $posted).
$posted  = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$old     = fn(string $k, string $d = '') => (string)($_POST[$k] ?? $d);
$checked = fn(string $k) => $posted && !empty($_POST[$k]) ? 'checked' : '';
$is_member_list = ($list_type ?? 'member') === 'member';
?>
<div class="mb-3">
    <a href="<?= e($return_to) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/coordinator/lists/create<?= $is_member_list ? '' : '?type=free' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="list_type" value="<?= e($list_type ?? 'member') ?>">
            <input type="hidden" name="return_to" value="<?= e($return_to) ?>">

            <!-- Name -->
            <div class="mb-4">
                <label for="list_name" class="form-label fw-semibold">Name der Liste</label>
                <input type="text" id="list_name" name="name"
                       class="form-control" maxlength="100" required
                       placeholder="z. B. Spiel gegen FC Beispiel" value="<?= e($old('name')) ?>">
            </div>

            <!-- Datum -->
            <div class="mb-4">
                <label for="list_date" class="form-label fw-semibold">Datum <span class="text-muted fw-normal">(optional)</span></label>
                <input type="date" id="list_date" name="date" class="form-control" value="<?= e($old('date')) ?>">
                <div class="form-text">z. B. Datum des Spiels oder Trainings<?= $is_member_list ? ' — bei einer Serie der erste Termin' : '' ?></div>
            </div>

            <!-- Uhrzeit -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Uhrzeit <span class="text-muted fw-normal">(optional)</span></label>
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <label for="list_time_start" class="form-label small text-muted mb-1">Beginn</label>
                        <input type="time" id="list_time_start" name="time_start" class="form-control" value="<?= e($old('time_start')) ?>">
                    </div>
                    <div class="pt-3 text-muted">–</div>
                    <div>
                        <label for="list_time_end" class="form-label small text-muted mb-1">Ende</label>
                        <input type="time" id="list_time_end" name="time_end" class="form-control" value="<?= e($old('time_end')) ?>">
                    </div>
                </div>
                <div class="form-text">Ohne Ende: Kalender zeigt 1 Stunde Dauer an.</div>
            </div>

            <?php if ($is_member_list): ?>
            <!-- Serie: mehrere eigenständige Listen bis zu einem Enddatum -->
            <div class="mb-4" data-series>
                <label for="list_repeat" class="form-label fw-semibold">Wiederholen <span class="text-muted fw-normal">(optional)</span></label>
                <select id="list_repeat" name="repeat" class="form-select mb-2">
                    <option value="">Nicht wiederholen</option>
                    <?php foreach (LIST_SERIES_REPEATS as $key => $r): ?>
                    <option value="<?= $key ?>" <?= $old('repeat') === $key ? 'selected' : '' ?>><?= e($r['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="list_repeat_until" class="form-label small text-muted mb-1">bis einschließlich</label>
                <input type="date" id="list_repeat_until" name="repeat_until" class="form-control" value="<?= e($old('repeat_until')) ?>">
                <div class="form-text" data-series-info aria-live="polite">
                    Legt für jeden Termin eine eigene Liste an, mit allen Einstellungen dieses Formulars.
                    Jede lässt sich danach einzeln bearbeiten oder löschen.
                </div>
            </div>
            <?php endif; ?>

            <!-- Ort -->
            <div class="mb-4">
                <label for="list_location" class="form-label fw-semibold">Ort <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" id="list_location" name="location"
                       class="form-control" maxlength="255"
                       placeholder="z. B. Sportplatz Mitte, Turnhalle" value="<?= e($old('location')) ?>">
            </div>

            <!-- Beschreibung -->
            <div class="mb-4">
                <label for="list_description" class="form-label fw-semibold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
                <textarea id="list_description" name="description" class="form-control" rows="2" maxlength="500"
                          placeholder="z. B. Heimspiel gegen FC Muster, Pokalrunde 2"><?= e($old('description')) ?></textarea>
            </div>

            <!-- Sichtbarkeit -->
            <div class="mb-4">
                <label for="list_visibility" class="form-label fw-semibold">Sichtbarkeit</label>
                <select id="list_visibility" name="visibility" class="form-select">
                    <option value="public"    <?= $old('visibility', 'public') === 'public'    ? 'selected' : '' ?>>Öffentlich — Mitglieder bearbeiten eigene Zeile</option>
                    <option value="protected" <?= $old('visibility') === 'protected' ? 'selected' : '' ?>>Geschützt — Mitglieder sehen eigene Zeile (nur lesen)</option>
                    <option value="private"   <?= $old('visibility') === 'private'   ? 'selected' : '' ?>>Privat — Nur für Koordinatoren sichtbar</option>
                </select>
            </div>

            <!-- Sichtbarkeit automatisch umstellen -->
            <div class="mb-4">
                <label for="auto_visibility" class="form-label fw-semibold">Sichtbarkeit automatisch umstellen <span class="text-muted fw-normal">(optional)</span></label>
                <select id="auto_visibility" name="auto_visibility" class="form-select mb-2">
                    <option value="">Nicht automatisch</option>
                    <?php foreach (['public', 'protected', 'private'] as $v): ?>
                    <option value="<?= $v ?>" <?= $old('auto_visibility') === $v ? 'selected' : '' ?>>auf <?= e(list_visibility_label($v)) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex align-items-center gap-2">
                    <input type="number" id="auto_visibility_hours" name="auto_visibility_hours"
                           class="form-control tm-input-hours" inputmode="numeric"
                           min="0" max="<?= LIST_AUTO_VISIBILITY_MAX_HOURS ?>" step="1" value="<?= e($old('auto_visibility_hours', '0')) ?>">
                    <label for="auto_visibility_hours" class="mb-0">Std. vor Beginn</label>
                </div>
                <div class="form-text">
                    z. B. „auf Geschützt, 2 Std. vor Beginn“ als Anmeldeschluss. Bei einer Serie gilt das
                    für jeden Termin. Ohne Uhrzeit zählt 00:00 als Beginn.
                </div>
            </div>

            <!-- Zeilen anderer Mitglieder / verstecken -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Anzeige</label>
                <?php if ($is_member_list): ?>
                <div class="form-check form-switch d-flex align-items-center gap-2">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="show_all_rows" id="show_all_rows" value="1" <?= $checked('show_all_rows') ?>>
                    <label class="form-check-label mb-0" for="show_all_rows">Mitglieder sehen Einträge anderer Mitglieder</label>
                </div>
                <?php endif; ?>
                <div class="form-check form-switch d-flex align-items-center gap-2">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="is_hidden" id="is_hidden" value="1" <?= $checked('is_hidden') ?>>
                    <label class="form-check-label mb-0" for="is_hidden">Liste verstecken (erscheint eingeklappt am Ende der Listenansicht)</label>
                </div>
            </div>

            <!-- Eigene Spalten -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Eigene Spalten <span class="text-muted fw-normal">(optional)</span></label>
                <div class="form-text mt-0 mb-2">Nur für diese Liste<?= $is_member_list ? ' — bei einer Serie in jeder Liste' : '' ?>. Leere Zeilen werden ignoriert.</div>
                <?php for ($k = 0; $k < LIST_CREATE_LOCAL_COLUMNS; $k++):
                    $ln = (string)($_POST['local_name'][$k] ?? '');
                    $lt = (string)($_POST['local_type'][$k] ?? 'boolean'); ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <input type="text" name="local_name[<?= $k ?>]" class="form-control flex-grow-1 tm-local-col-name"
                           maxlength="100" placeholder="Spaltenname" aria-label="Name der eigenen Spalte <?= $k + 1 ?>" value="<?= e($ln) ?>">
                    <select name="local_type[<?= $k ?>]" class="form-select w-auto" aria-label="Typ der eigenen Spalte <?= $k + 1 ?>">
                        <option value="boolean" <?= $lt === 'boolean' ? 'selected' : '' ?>>Ja/Nein</option>
                        <option value="number"  <?= $lt === 'number'  ? 'selected' : '' ?>>Zahl</option>
                        <option value="text"    <?= $lt === 'text'    ? 'selected' : '' ?>>Text</option>
                    </select>
                    <div class="form-check form-switch d-flex align-items-center gap-2 mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" value="1"
                               name="local_coach[<?= $k ?>]" id="local_coach_<?= $k ?>" <?= !empty($_POST['local_coach'][$k]) ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0 small" for="local_coach_<?= $k ?>">nur Koordinatoren</label>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Section 4: Global column selection with optional default values (member lists only) -->
            <?php
            $system_columns = $system_columns ?? [];
            $has_any_columns = (($list_type ?? 'member') === 'member') && (!empty($global_columns) || !empty($system_columns));
            ?>
            <?php if ($has_any_columns): ?>
            <div class="mb-4">
                <label class="form-label fw-semibold">Globale Spalten auswählen</label>
                <div class="form-text mb-2">
                    Welche globalen Spalten sollen in dieser Liste erscheinen?
                    Optional: Standardwert vorausfüllen (gilt für alle Mitglieder beim Erstellen).
                </div>

                <?php if (!empty($system_columns)): ?>
                <p class="text-muted small mb-2"><i class="bi bi-lock-fill me-1"></i>Systemspalten</p>
                <?php foreach ($system_columns as $col): ?>
                <?php $col_id = (int)$col['id']; ?>
                <div class="mb-3">
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="global_columns[]" value="<?= $col_id ?>"
                               id="col_<?= $col_id ?>" <?= !$posted || in_array($col_id, array_map('intval', (array)($_POST['global_columns'] ?? [])), true) ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0" for="col_<?= $col_id ?>">
                            <?= e($col['name']) ?>
                            <span class="badge bg-light text-dark border ms-1">
                                <?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>
                            </span>
                            <span class="badge bg-secondary-subtle text-secondary ms-1">
                                <i class="bi bi-lock me-1"></i>System
                            </span>
                        </label>
                    </div>
                    <div class="ms-4 mt-1">
                        <?php if ($col['data_type'] === 'boolean'): ?>
                        <div class="form-check form-switch d-flex align-items-center gap-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="defaults[<?= $col_id ?>]" value="1"
                                   id="default_<?= $col_id ?>" <?= !empty($_POST['defaults'][$col_id]) ? 'checked' : '' ?>>
                            <label class="form-check-label mb-0 text-muted small" for="default_<?= $col_id ?>">
                                Standardwert: Ja
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="input-group">
                            <span class="input-group-text text-muted small">Standard</span>
                            <input type="number" step="any"
                                   name="defaults[<?= $col_id ?>]"
                                   class="form-control"
                                   placeholder="leer lassen = kein Standardwert" value="<?= e((string)($_POST['defaults'][$col_id] ?? '')) ?>">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($global_columns)): ?>
                <?php if (!empty($system_columns)): ?>
                <p class="text-muted small mb-2 mt-3">Team-Spalten</p>
                <?php endif; ?>
                <?php foreach ($global_columns as $col): ?>
                <?php $col_id = (int)$col['id']; ?>
                <div class="mb-3">
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="global_columns[]" value="<?= $col_id ?>"
                               id="col_<?= $col_id ?>" <?= !$posted || in_array($col_id, array_map('intval', (array)($_POST['global_columns'] ?? [])), true) ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0" for="col_<?= $col_id ?>">
                            <?= e($col['name']) ?>
                            <span class="badge bg-light text-dark border ms-1">
                                <?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>
                            </span>
                        </label>
                    </div>
                    <!-- Default value for this column (optional, only applied at list creation) -->
                    <div class="ms-4 mt-1">
                        <?php if ($col['data_type'] === 'boolean'): ?>
                        <div class="form-check form-switch d-flex align-items-center gap-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="defaults[<?= $col_id ?>]" value="1"
                                   id="default_<?= $col_id ?>" <?= !empty($_POST['defaults'][$col_id]) ? 'checked' : '' ?>>
                            <label class="form-check-label mb-0 text-muted small" for="default_<?= $col_id ?>">
                                Standardwert: Ja
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="input-group">
                            <span class="input-group-text text-muted small">Standard</span>
                            <input type="number" step="any"
                                   name="defaults[<?= $col_id ?>]"
                                   class="form-control"
                                   placeholder="leer lassen = kein Standardwert" value="<?= e((string)($_POST['defaults'][$col_id] ?? '')) ?>">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php elseif (($list_type ?? 'member') === 'member'): ?>
            <div class="mb-4">
                <p class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Noch keine globalen Spalten definiert.
                    <a href="/coordinator/columns">Spalten anlegen</a>
                </p>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary min-touch" data-series-submit>Liste anlegen</button>
            <a href="<?= e($return_to) ?>" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
        </form>
    </div>
</div>

<?php if ($is_member_list): ?>
<script>
// Serie: Anzahl Termine live anzeigen (gleiche Rechnung wie list_series_dates() in PHP).
// Nur Anzeige — der Server rechnet beim Speichern selbst und prüft die Grenzen.
(function () {
    var box = document.querySelector('[data-series]');
    if (!box) return;
    var rep = document.getElementById('list_repeat'), until = document.getElementById('list_repeat_until');
    var start = document.getElementById('list_date'), info = box.querySelector('[data-series-info]');
    var submit = document.querySelector('[data-series-submit]');
    var plain = info.textContent, months = { monthly: 1, quarterly: 3, yearly: 12 }, max = <?= LIST_SERIES_MAX ?>;
    var wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    function iso(d) { return d.toISOString().slice(0, 10); }
    function nth(s, r, k) {
        var d = new Date(s + 'T00:00:00Z');
        if (r === 'weekly') { d.setUTCDate(d.getUTCDate() + 7 * k); return d; }
        var day = d.getUTCDate(), m = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + k * months[r], 1));
        var last = new Date(Date.UTC(m.getUTCFullYear(), m.getUTCMonth() + 1, 0)).getUTCDate();
        m.setUTCDate(Math.min(day, last)); return m;
    }
    function update() {
        var r = rep.value;
        until.disabled = !r;
        if (!r) { info.textContent = plain; info.classList.remove('text-danger'); if (submit) submit.textContent = 'Liste anlegen'; return; }
        if (!start.value || !until.value) { info.textContent = 'Datum (erster Termin) und Enddatum wählen.'; info.classList.remove('text-danger'); return; }
        var n = 0, last = null;
        while (n <= max) { var d = nth(start.value, r, n); if (iso(d) > until.value) break; last = d; n++; }
        var bad = n < 2 || n > max;
        info.classList.toggle('text-danger', bad);
        info.textContent = n > max ? 'Mehr als ' + max + ' Termine — wähle ein früheres Enddatum.'
            : n < 2 ? 'Bis zu diesem Datum gibt es nur einen Termin.'
            : n + ' Termine, letzter am ' + wd[last.getUTCDay()] + ' ' + iso(last).split('-').reverse().join('.') + '.';
        if (submit) submit.textContent = bad ? 'Liste anlegen' : n + ' Listen anlegen';
    }
    [rep, until, start].forEach(function (el) { el.addEventListener('input', update); el.addEventListener('change', update); });
    update();
})();
</script>
<?php endif; ?>
