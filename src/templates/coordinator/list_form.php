<?php
// src/templates/coach/list_form.php — Create list form
// Variables: $error (string), $global_columns (array of global column rows), $list_type (string)
?>
<div class="mb-3">
    <a href="/coordinator/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Listen
    </a>
</div>

<?php if ($_GET['success'] ?? null): render_flash('success', 'Gespeichert.'); endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/coordinator/lists/create">
            <?= csrf_field() ?>
            <input type="hidden" name="list_type" value="<?= e($list_type ?? 'member') ?>">

            <!-- Section 1: Name -->
            <div class="mb-4">
                <label for="list_name" class="form-label fw-semibold">Name der Liste</label>
                <input type="text" id="list_name" name="name"
                       class="form-control" maxlength="100" required
                       placeholder="z. B. Spiel gegen FC Beispiel">
            </div>

            <!-- Section: Date (optional) -->
            <div class="mb-4">
                <label for="list_date" class="form-label fw-semibold">Datum <span class="text-muted fw-normal">(optional)</span></label>
                <input type="date" id="list_date" name="date" class="form-control">
                <div class="form-text">z. B. Datum des Spiels oder Trainings</div>
            </div>

            <!-- Section: Start and end time (optional) — shown when date is set -->
            <?php if (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES): ?>
            <div class="mb-4">
                <label class="form-label fw-semibold">Uhrzeit <span class="text-muted fw-normal">(optional)</span></label>
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <label for="list_time_start" class="form-label small text-muted mb-1">Beginn</label>
                        <input type="time" id="list_time_start" name="time_start" class="form-control"
                               value="">
                    </div>
                    <div class="pt-3 text-muted">–</div>
                    <div>
                        <label for="list_time_end" class="form-label small text-muted mb-1">Ende</label>
                        <input type="time" id="list_time_end" name="time_end" class="form-control"
                               value="">
                    </div>
                </div>
                <div class="form-text">Ohne Ende: Kalender zeigt 1 Stunde Dauer an.</div>
            </div>
            <?php endif; ?>

            <!-- Section: Location (optional) — per D-15, D-16 -->
            <div class="mb-4">
                <label for="list_location" class="form-label fw-semibold">Ort <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" id="list_location" name="location"
                       class="form-control" maxlength="255"
                       placeholder="z. B. Sportplatz Mitte, Turnhalle">
                <div class="form-text">z. B. Sportplatz Mitte, Turnhalle Schule</div>
            </div>

            <!-- Section: Description (optional) -->
            <div class="mb-4">
                <label for="list_desc" class="form-label fw-semibold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
                <textarea id="list_desc" name="description" class="form-control" rows="2" maxlength="500"
                          placeholder="z. B. Heimspiel gegen FC Muster, Pokalrunde 2"></textarea>
            </div>

            <!-- Section 2: Visibility -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Sichtbarkeit</label>
                <select name="visibility" class="form-select">
                    <option value="public">Öffentlich — Mitglieder bearbeiten eigene Zeile</option>
                    <option value="protected">Geschützt — Mitglieder sehen eigene Zeile (nur lesen)</option>
                    <option value="private">Privat — Nur für Koordinatoren sichtbar</option>
                </select>
                <div class="form-text">
                    Du kannst die Sichtbarkeit später jederzeit unter "Einstellungen" ändern.
                </div>
            </div>

            <!-- Section 3: Row visibility -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Zeilen anderer Mitglieder</label>
                <div class="form-check form-switch d-flex align-items-center gap-2">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="show_all_rows" id="show_all_rows" value="1">
                    <label class="form-check-label mb-0" for="show_all_rows">
                        Mitglieder sehen Einträge anderer Mitglieder
                    </label>
                </div>
                <div class="form-text">
                    Standard: Mitglieder sehen nur ihre eigene Zeile. Diese Einstellung ist später änderbar.
                </div>
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
                               id="col_<?= $col_id ?>" checked>
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
                                   id="default_<?= $col_id ?>">
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
                                   placeholder="leer lassen = kein Standardwert">
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
                               id="col_<?= $col_id ?>" checked>
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
                                   id="default_<?= $col_id ?>">
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
                                   placeholder="leer lassen = kein Standardwert">
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

            <button type="submit" class="btn btn-primary min-touch">Liste anlegen</button>
            <a href="/coordinator/lists" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
        </form>
    </div>
</div>

<div class="mt-4">
    <a href="/coordinator/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Listen
    </a>
</div>
