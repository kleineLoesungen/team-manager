<?php
// src/templates/admin/organization_edit.php — Edit organization form
// Variables: $organization (array with id, name), $error (string)
?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Änderungen gespeichert.'); ?>
<?php endif; ?>
<?php if (!empty($error)): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>

<?php render_page_header('Organisation bearbeiten', '/admin/organizations'); ?>

<form method="POST" action="/admin/organizations/<?= (int)$organization['id'] ?>/edit">
    <?= csrf_field() ?>
    <div class="mb-4">
        <label for="organization_name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
        <input type="text"
               id="organization_name"
               name="name"
               class="form-control min-touch"
               value="<?= e($organization['name']) ?>"
               required
               maxlength="100"
               autofocus>
    </div>
    <div class="d-grid gap-3">
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-check-lg me-1"></i>Organisation speichern
        </button>
        <a href="/admin/organizations" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>
