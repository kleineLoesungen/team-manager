<?php
// src/templates/admin/team_form.php — Standalone team edit form
// Variables: $team (array with id, name), $error (string)
?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<form method="POST" action="/admin/teams/<?= e($team['id']) ?>/edit">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="team_name" class="form-label fw-semibold small">Teamname</label>
        <input type="text"
               class="form-control min-touch"
               id="team_name"
               name="team_name"
               value="<?= e($team['name']) ?>"
               required
               maxlength="100">
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/teams" class="btn btn-outline-secondary">Abbrechen</a>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </div>
</form>
