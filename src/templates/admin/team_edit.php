<?php
// src/templates/admin/team_edit.php — Edit team form
// Variables: $team (array with id, name, sort_order), $error (string)
?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="mb-3">
    <a href="/admin/teams" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<form method="POST" action="/admin/teams/<?= (int)$team['id'] ?>/edit">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="team_name" class="form-label fw-semibold">Teamname <span class="text-danger">*</span></label>
        <input type="text"
               id="team_name"
               name="team_name"
               class="form-control min-touch"
               value="<?= e($team['name']) ?>"
               required
               maxlength="100"
               autofocus>
    </div>
    <div class="mb-4">
        <label for="sort_order" class="form-label fw-semibold">Sortiernummer</label>
        <input type="number"
               id="sort_order"
               name="sort_order"
               class="form-control"
               value="<?= (int)$team['sort_order'] ?>"
               min="0"
               step="1">
        <div class="form-text">Niedrigere Zahl = weiter oben. 0 = keine Sortierung.</div>
    </div>
    <button type="submit" class="btn btn-primary min-touch">
        <i class="bi bi-check-lg me-1"></i>Speichern
    </button>
</form>
