<?php
// src/templates/coach/list_form.php — Create list form
// Per D-11: three sections: name, visibility, global column selection (informational)
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

            <!-- Section 2: Visibility -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Sichtbarkeit</label>
                <select name="visibility" class="form-select">
                    <option value="public">Öffentlich — Spieler können eigene Zeile bearbeiten</option>
                    <option value="protected">Geschützt — Trainer schreibt; Spieler sehen nichts</option>
                    <option value="private">Privat — Nur für Trainer sichtbar</option>
                </select>
                <div class="form-text">
                    Sie können die Sichtbarkeit später jederzeit unter "Einstellungen" ändern.
                </div>
            </div>

            <!-- Section 3: Global columns (informational) -->
            <?php if (!empty($global_columns)): ?>
            <div class="mb-4">
                <label class="form-label fw-semibold">Verfügbare globale Spalten</label>
                <div class="form-text mb-2">
                    Diese Spalten stehen in allen Listen Ihres Teams zur Verfügung.
                </div>
                <?php foreach ($global_columns as $col): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="global_columns[]" value="<?= (int)$col['id'] ?>"
                           id="col_<?= (int)$col['id'] ?>" checked>
                    <label class="form-check-label" for="col_<?= (int)$col['id'] ?>">
                        <?= e($col['name']) ?>
                        <span class="badge bg-light text-dark border ms-1">
                            <?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>
                        </span>
                    </label>
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
