<?php
// src/templates/coach/player_form.php — New player creation form
// Per D-12: Vorname + Nachname fields (username auto-generated, not shown here)
// Per D-09: errors passed via $error variable (PRG pattern on POST errors)
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="/coordinator/members/create" class="row g-3" style="max-width: 480px;">
    <?= csrf_field() ?>

    <div class="col-12">
        <label for="first_name" class="form-label">Vorname <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control form-control-lg"
               id="first_name"
               name="first_name"
               required
               autocomplete="given-name"
               value="<?= e($_POST['first_name'] ?? '') ?>">
    </div>

    <div class="col-12">
        <label for="last_name" class="form-label">Nachname <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control form-control-lg"
               id="last_name"
               name="last_name"
               required
               autocomplete="family-name"
               value="<?= e($_POST['last_name'] ?? '') ?>">
    </div>

    <div class="col-12">
        <p class="text-muted small">
            <i class="bi bi-info-circle me-1"></i>
            Benutzername und Passwort werden automatisch generiert und einmalig angezeigt.
        </p>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-person-plus me-1"></i>Mitglied anlegen
        </button>
        <a href="/coordinator/members" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>
