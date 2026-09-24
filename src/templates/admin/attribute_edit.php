<?php
// src/templates/admin/attribute_edit.php
// Variables: $attr (array), $group_id (int), $error (string)
?>
<div class="mb-3">
    <a href="/admin/attributes" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<p class="text-muted small mb-3"><?= e($attr['group_name']) ?></p>

<?php if ($error): render_flash('error', $error); endif; ?>

<form method="POST" action="/admin/attributes/<?= $group_id ?>/attributes/<?= (int)$attr['id'] ?>/edit">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="card mb-3">
        <div class="card-header fw-semibold">Attribut</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label for="attr_name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="attr_name" name="name" class="form-control"
                           value="<?= e($attr['name']) ?>" maxlength="100" required>
                </div>
                <div class="col-6">
                    <label for="attr_type" class="form-label">Typ</label>
                    <select id="attr_type" name="data_type" class="form-select">
                        <option value="text" <?= ($attr['data_type'] ?? 'text') === 'text' ? 'selected' : '' ?>>Text</option>
                        <option value="date" <?= ($attr['data_type'] ?? 'text') === 'date' ? 'selected' : '' ?>>Datum</option>
                    </select>
                </div>
                <div class="col-6">
                    <label for="attr_order" class="form-label">Reihenfolge</label>
                    <input type="number" id="attr_order" name="sort_order" class="form-control"
                           value="<?= (int)$attr['sort_order'] ?>" min="0">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">Berechtigungen</div>
        <div class="card-body">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="visible_to_player" name="visible_to_player"
                       <?= $attr['visible_to_player'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="visible_to_player">
                    Für Mitglied sichtbar
                </label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="editable_by_player" name="editable_by_player"
                       <?= $attr['editable_by_player'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="editable_by_player">
                    Von Mitglied editierbar
                </label>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary min-touch">
        <i class="bi bi-floppy me-1"></i>Attribut speichern
    </button>
</form>

<div class="card tm-danger-zone mt-4">
    <div class="card-body">
        <h2 class="card-title">Attribut löschen</h2>
        <p class="card-text small text-muted mb-3">
            Alle gespeicherten Werte für dieses Attribut bei allen Mitgliedern gehen unwiderruflich verloren.
        </p>
        <form method="POST" action="/admin/attributes/<?= $group_id ?>/attributes/<?= (int)$attr['id'] ?>/edit"
              onsubmit="return confirm('Attribut „<?= e($attr['name']) ?>" wirklich löschen?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-outline-danger min-touch">
                <i class="bi bi-trash me-1"></i>Attribut löschen
            </button>
        </form>
    </div>
</div>
