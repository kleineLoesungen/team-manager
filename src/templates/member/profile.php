<?php
// src/templates/member/profile.php — Member profile: email address management
// Variables (from handler via use()): $current_email (string), $error (string), $success (string)
// Per UI spec Screen 3: card layout, optional email input, Bootstrap 5.3 classes.
?>

<?php if ($error): ?>
<div class="alert alert-danger mb-3">
    <?= e($error) ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success mb-3">
    <?= e($success) ?>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h6 fw-semibold mb-3">E-Mail-Adresse</h2>
        <form method="POST" action="/member/profile">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="email" class="form-label">
                    E-Mail-Adresse <span class="text-muted small">(optional)</span>
                </label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control"
                       value="<?= e($current_email) ?>"
                       maxlength="255"
                       placeholder="deine@email.de">
                <div class="form-text">
                    Wird genutzt, um dich über neue Listen und Inhalte zu informieren.
                </div>
            </div>
            <button type="submit" class="btn btn-primary min-touch">Speichern</button>
        </form>
    </div>
</div>
