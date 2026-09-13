<?php
// src/templates/admin/team_create.php — Create team form
// Variables: $error (string)
?>
<?php if (!empty($error)): render_flash('error', $error); endif; ?>

<div class="mb-3">
    <a href="/admin/teams" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<form method="POST" action="/admin/teams/create">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="team_name" class="form-label fw-semibold">Teamname <span class="text-danger">*</span></label>
        <input type="text"
               id="team_name"
               name="team_name"
               class="form-control min-touch"
               required
               maxlength="100"
               placeholder="z.B. U17 Herren"
               autofocus>
    </div>
    <div class="mb-4">
        <label for="sort_order" class="form-label fw-semibold">Sortiernummer</label>
        <input type="number"
               id="sort_order"
               name="sort_order"
               class="form-control"
               value="0"
               min="0"
               step="1">
        <div class="form-text">Niedrigere Zahl = weiter oben. 0 = keine Sortierung.</div>
    </div>
    <button type="submit" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Team erstellen
    </button>
</form>

<div class="mt-4">
    <a href="/admin/teams" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>
