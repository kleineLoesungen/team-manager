<?php
// src/templates/admin/column_delete_confirm.php — Two-step deletion confirm
// Variables: $column (array with id, name, data_type)
?>
<div class="mb-3">
    <a href="/admin/columns" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Systemspalten
    </a>
</div>

<div class="card shadow-sm" style="max-width: 480px;">
    <div class="card-body text-center py-4">
        <i class="bi bi-exclamation-triangle-fill text-danger display-5 mb-3 d-block"></i>
        <h5 class="fw-bold mb-2">Systemspalte löschen?</h5>
        <p class="mb-1">
            Bist du sicher, dass du die Systemspalte
            <strong><?= e($column['name']) ?></strong>
            (<?= $column['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>)
            löschen möchtest?
        </p>
        <p class="text-muted small mb-4">
            Löschen schlägt fehl, wenn noch Zellen in Listen auf diese Spalte verweisen.
        </p>
        <form method="POST" action="/admin/columns/<?= (int)$column['id'] ?>/delete" class="d-inline me-2">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash me-1"></i>Ja, löschen
            </button>
        </form>
        <a href="/admin/columns" class="btn btn-outline-secondary">Abbrechen</a>
    </div>
</div>
