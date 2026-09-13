<?php
// src/templates/coordinator/file_form.php — create new file form
?>
<div class="mb-3">
    <a href="/coordinator/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Listen
    </a>
</div>

<form method="POST" action="/coordinator/files/create">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="name" class="form-label fw-semibold">Name</label>
        <input type="text" id="name" name="name" class="form-control"
               maxlength="255" required
               value="<?= e($_POST['name'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label for="date" class="form-label fw-semibold">Datum <span class="text-muted fw-normal">(optional)</span></label>
        <input type="date" id="date" name="date" class="form-control"
               value="<?= e($_POST['date'] ?? '') ?>">
        <div class="form-text">Wird für die Sortierung in der Übersicht verwendet.</div>
    </div>

    <div class="mb-3">
        <label for="visibility" class="form-label fw-semibold">Sichtbarkeit</label>
        <select id="visibility" name="visibility" class="form-select">
            <option value="public"    <?= (($_POST['visibility'] ?? 'public') === 'public')    ? 'selected' : '' ?>>Öffentlich — Mitglieder können lesen und bearbeiten</option>
            <option value="protected" <?= (($_POST['visibility'] ?? '') === 'protected') ? 'selected' : '' ?>>Geschützt — Mitglieder können nur lesen</option>
            <option value="private"   <?= (($_POST['visibility'] ?? '') === 'private')   ? 'selected' : '' ?>>Privat — Nur Koordinator</option>
        </select>
    </div>

    <div class="mb-4 form-check form-switch d-flex align-items-center gap-2">
        <input type="checkbox" class="form-check-input" id="is_hidden" name="is_hidden"
               <?= isset($_POST['is_hidden']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_hidden">Versteckt (in Übersicht einklappen)</label>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch">Anlegen</button>
        <a href="/coordinator/lists" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>

<div class="mt-4">
    <a href="/coordinator/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Listen
    </a>
</div>
