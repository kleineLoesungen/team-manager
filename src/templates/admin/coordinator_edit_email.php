<?php
// src/templates/admin/coordinator_edit_email.php
// Variables: $coordinator (array with id, first_name, last_name, email), $error (string), $success (string)
?>

<div class="mb-3">
    <a href="/admin/coordinators" class="btn btn-sm btn-outline-secondary">
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
            Koordinator: <strong><?= e($coordinator['first_name'] . ' ' . $coordinator['last_name']) ?></strong>
        </p>
        <form method="POST" action="/admin/coordinators/<?= (int)$coordinator['id'] ?>/edit-email">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="coordinator_email" class="form-label">
                    E-Mail-Adresse <span class="text-muted small">(optional)</span>
                </label>
                <input type="email"
                       id="coordinator_email"
                       name="email"
                       class="form-control min-touch"
                       value="<?= e($coordinator['email'] ?? '') ?>"
                       maxlength="255"
                       placeholder="koordinator@email.de">
                <div class="form-text">
                    Wird für die Koordinatoren-Benachrichtigung genutzt. Nur für den Admin sichtbar.
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
                <a href="/admin/coordinators" class="btn btn-outline-secondary min-touch">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
