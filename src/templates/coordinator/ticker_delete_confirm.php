<?php
// src/templates/coordinator/ticker_delete_confirm.php
// Variables: $ticker (array with id, name)
?>
<div class="card border-danger shadow-sm" style="max-width: 500px;">
    <div class="card-header bg-danger text-white fw-semibold">
        <i class="bi bi-exclamation-triangle me-2"></i>Ticker löschen
    </div>
    <div class="card-body">
        <p class="mb-2">Du bist dabei, den Ticker <strong><?= e($ticker['name']) ?></strong> zu löschen.</p>
        <p class="text-danger mb-4">Dieser Ticker und alle Nachrichten werden dauerhaft gelöscht. Diese Aktion kann nicht rückgängig gemacht werden.</p>
        <form method="POST" action="/coordinator/ticker/<?= (int)$ticker['id'] ?>/delete">
            <?= csrf_field() ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">Jetzt löschen</button>
                <a href="/coordinator/ticker/<?= (int)$ticker['id'] ?>" class="btn btn-outline-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
