<?php
// src/templates/admin/club_form.php — Create club form
// Variables: $error (string), $name (string)
?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width:480px">
    <div class="card-body">
        <form method="POST" action="/admin/clubs/create">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="club_name" class="form-label fw-semibold small">Klubname</label>
                <input type="text"
                       class="form-control min-touch"
                       id="club_name"
                       name="name"
                       value="<?= e($name) ?>"
                       maxlength="100"
                       required
                       placeholder="z.B. FC Musterstadt">
            </div>
            <div class="d-flex gap-2">
                <a href="/admin/clubs" class="btn btn-outline-secondary">Abbrechen</a>
                <button type="submit" class="btn btn-primary">Klub erstellen</button>
            </div>
        </form>
    </div>
</div>
