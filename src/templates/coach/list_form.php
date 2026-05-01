<?php
// src/templates/coach/list_form.php — Create list form
// Variables: $error (string), $global_columns (array of global column rows)
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width: 600px;">
    <div class="card-body">
        <form method="POST" action="/coach/lists/create">
            <?= csrf_field() ?>

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
                    <option value="public">Öffentlich — Spieler bearbeiten eigene Zeile</option>
                    <option value="protected">Geschützt — Spieler sehen eigene Zeile (nur lesen)</option>
                    <option value="private">Privat — Nur für Trainer sichtbar</option>
                </select>
                <div class="form-text">
                    Sie können die Sichtbarkeit später jederzeit unter "Einstellungen" ändern.
                </div>
            </div>

            <!-- Section 3: Row visibility -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Zeilen anderer Spieler</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="show_all_rows"
                           id="show_all_rows" value="1">
                    <label class="form-check-label" for="show_all_rows">
                        Spieler sehen Einträge anderer Spieler
                    </label>
                </div>
                <div class="form-text">
                    Standard: Spieler sehen nur ihre eigene Zeile. Diese Einstellung ist später änderbar.
                </div>
            </div>

            <!-- Section 4: Global column selection with optional default values -->
            <?php if (!empty($global_columns)): ?>
            <div class="mb-4">
                <label class="form-label fw-semibold">Globale Spalten auswählen</label>
                <div class="form-text mb-2">
                    Welche globalen Spalten sollen in dieser Liste erscheinen?
                    Optional: Standardwert vorausfüllen (gilt für alle Spieler beim Erstellen).
                </div>
                <?php foreach ($global_columns as $col): ?>
                <?php $col_id = (int)$col['id']; ?>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="global_columns[]" value="<?= $col_id ?>"
                               id="col_<?= $col_id ?>" checked>
                        <label class="form-check-label" for="col_<?= $col_id ?>">
                            <?= e($col['name']) ?>
                            <span class="badge bg-light text-dark border ms-1">
                                <?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>
                            </span>
                        </label>
                    </div>
                    <!-- Default value for this column (optional, only applied at list creation) -->
                    <div class="ms-4 mt-1">
                        <?php if ($col['data_type'] === 'boolean'): ?>
                        <div class="form-check form-check-sm">
                            <input class="form-check-input" type="checkbox"
                                   name="defaults[<?= $col_id ?>]" value="1"
                                   id="default_<?= $col_id ?>">
                            <label class="form-check-label text-muted small" for="default_<?= $col_id ?>">
                                Standardwert: Ja
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="input-group input-group-sm" style="max-width: 200px;">
                            <span class="input-group-text text-muted small">Standard</span>
                            <input type="number" step="any"
                                   name="defaults[<?= $col_id ?>]"
                                   class="form-control form-control-sm"
                                   placeholder="leer lassen = kein Standardwert">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="mb-4">
                <p class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Noch keine globalen Spalten definiert.
                    <a href="/coach/columns">Spalten anlegen</a>
                </p>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary min-touch">Liste anlegen</button>
            <a href="/coach/lists" class="btn btn-outline-secondary ms-2 min-touch">Abbrechen</a>
        </form>
    </div>
</div>
