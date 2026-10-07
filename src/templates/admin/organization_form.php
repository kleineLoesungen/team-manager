<?php
// src/templates/admin/organization_form.php — Create organization form
// Variables: $error (string), $name (string)
?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Organisation angelegt.'); ?>
<?php endif; ?>
<?php if (!empty($error)): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>

<?php render_page_header('Organisation hinzufügen', '/admin/organizations'); ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/admin/organizations/create">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="organization_name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                <input type="text"
                       class="form-control min-touch"
                       id="organization_name"
                       name="name"
                       value="<?= e($name) ?>"
                       maxlength="100"
                       required
                       autofocus
                       placeholder="z.B. FC Musterstadt">
            </div>
            <div class="d-grid gap-3">
                <button type="submit" class="btn btn-primary min-touch">Organisation anlegen</button>
                <a href="/admin/organizations" class="btn btn-outline-secondary min-touch">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
