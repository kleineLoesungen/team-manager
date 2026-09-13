<?php
// src/templates/coordinator/ticker_delete_confirm.php
// Variables: $ticker (array with id, name)
?>
<div class="mb-3">
    <a href="/coordinator/ticker/<?= (int)$ticker['id'] ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<h1 class="h2 mb-3">Möchtest du diesen Ticker wirklich löschen?</h1>
<p class="text-body-secondary mb-2"><strong><?= e($ticker['name']) ?></strong> und alle Nachrichten werden dauerhaft gelöscht.</p>
<p class="text-body-secondary mb-4">Diese Aktion kann nicht rückgängig gemacht werden.</p>

<form method="POST" action="/coordinator/ticker/<?= (int)$ticker['id'] ?>/delete">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-danger w-100 mb-2">
        <i class="bi bi-trash me-1"></i>Ja, löschen
    </button>
</form>
<a href="/coordinator/ticker" class="btn btn-outline-secondary w-100">Abbrechen</a>
