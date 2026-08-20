<?php
// src/templates/coordinator/ticker_delete_confirm.php
// Variables: $ticker (array with id, name)
?>
<div class="mb-3">
    <a href="/coordinator/ticker/<?= (int)$ticker['id'] ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<div class="alert alert-danger mb-4" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong><?= e($ticker['name']) ?></strong> und alle Nachrichten werden dauerhaft gelöscht.
    Diese Aktion kann nicht rückgängig gemacht werden.
</div>

<form method="POST" action="/coordinator/ticker/<?= (int)$ticker['id'] ?>/delete">
    <?= csrf_field() ?>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash me-1"></i>Jetzt löschen
        </button>
        <a href="/coordinator/ticker/<?= (int)$ticker['id'] ?>"
           class="btn btn-outline-secondary">Abbrechen</a>
    </div>
</form>
