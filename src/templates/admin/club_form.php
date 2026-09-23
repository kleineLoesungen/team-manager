<?php
// src/templates/admin/club_form.php — Create club form
// Variables: $error (string), $name (string)
?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Klub erfolgreich erstellt.'); ?>
<?php endif; ?>
<?php if (!empty($error)): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>

<?php render_page_header('Klub hinzufügen', '/admin/clubs'); ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/admin/clubs/create">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="club_name" class="form-label fw-semibold">Klubname <span class="text-danger">*</span></label>
                <input type="text"
                       class="form-control min-touch"
                       id="club_name"
                       name="name"
                       value="<?= e($name) ?>"
                       maxlength="100"
                       required
                       autofocus
                       placeholder="z.B. FC Musterstadt">
            </div>
            <div class="d-grid gap-3">
                <button type="submit" class="btn btn-primary min-touch">Klub erstellen</button>
                <a href="/admin/clubs" class="btn btn-outline-secondary min-touch">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
