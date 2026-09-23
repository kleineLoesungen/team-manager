<?php
// src/templates/admin/club_edit.php — Edit club form
// Variables: $club (array with id, name), $error (string)
?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Änderungen gespeichert.'); ?>
<?php endif; ?>
<?php if (!empty($error)): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>

<?php render_page_header('Klub bearbeiten', '/admin/clubs'); ?>

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
    <div class="d-grid gap-3">
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-check-lg me-1"></i>Klub speichern
        </button>
        <a href="/admin/clubs" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>
