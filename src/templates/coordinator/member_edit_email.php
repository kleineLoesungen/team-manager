<?php
// src/templates/coordinator/member_edit_email.php
// Variables: $member (array with id, first_name, last_name, email), $error (string), $success (string)
?>

<div class="mb-3">
    <a href="/coordinator/members" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zur Übersicht
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-3"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success mb-3"><?= e($success) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <p class="text-muted mb-3">
            Mitglied: <strong><?= e($member['first_name'] . ' ' . $member['last_name']) ?></strong>
        </p>
        <form method="POST" action="/coordinator/members/<?= (int)$member['id'] ?>/edit-email">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="member_email" class="form-label">
                    E-Mail-Adresse <span class="text-muted small">(optional)</span>
                </label>
                <input type="email"
                       id="member_email"
                       name="email"
                       class="form-control min-touch"
                       value="<?= e($member['email'] ?? '') ?>"
                       maxlength="255"
                       placeholder="mitglied@email.de">
                <div class="form-text">
                    Wird für Benachrichtigungen genutzt. Nur für den Koordinator sichtbar.
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
                <a href="/coordinator/members" class="btn btn-outline-secondary min-touch">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
