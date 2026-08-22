<?php
// src/templates/admin/column_rename.php
// Variables: $column (array), $error (string)
?>
<div class="mb-3">
    <a href="/admin/columns" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card mb-4" style="max-width: 500px;">
    <div class="card-header">
        <span class="fw-semibold">Spalte umbenennen</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Typ: <strong><?= $column['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?></strong>
            — Typen können nicht geändert werden.
        </p>
        <form method="POST" action="/admin/columns/<?= (int)$column['id'] ?>/rename">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="col_name" class="form-label">Neuer Name</label>
                <input type="text" id="col_name" name="name" class="form-control"
                       value="<?= e($column['name']) ?>" maxlength="100" required autofocus>
                <div class="form-text">
                    Stimmt der neue Name mit einer bestehenden Systemspalte gleichen Typs überein,
                    werden die Spalten zusammengeführt (du wirst vorher um Bestätigung gebeten).
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Umbenennen</button>
        </form>
    </div>
</div>
