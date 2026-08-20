<?php
// src/templates/admin/club_edit.php — Edit club form
// Variables: $club (array with id, name), $error (string)
?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="mb-3">
    <a href="/admin/clubs" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<form method="POST" action="/admin/clubs/<?= (int)$club['id'] ?>/edit">
    <?= csrf_field() ?>
    <div class="mb-4">
        <label for="club_name" class="form-label fw-semibold">Klubname <span class="text-danger">*</span></label>
        <input type="text"
               id="club_name"
               name="name"
               class="form-control min-touch"
               value="<?= e($club['name']) ?>"
               required
               maxlength="100"
               autofocus>
    </div>
    <button type="submit" class="btn btn-primary min-touch">
        <i class="bi bi-check-lg me-1"></i>Speichern
    </button>
</form>

<div class="mt-4">
    <a href="/admin/clubs" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>
