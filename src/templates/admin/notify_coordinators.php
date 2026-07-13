<?php
// src/templates/admin/notify_coordinators.php — Admin notify coordinators page
// Variables: $with_email (array), $without_email (array), $error (string), $success (string)
// Per UI spec Screen 4.
?>

<?php if ($error): ?>
<div class="alert alert-danger mb-3"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success mb-3"><?= $success ?></div>
<?php endif; ?>

<?php if (empty($with_email)): ?>
<div class="alert alert-info">
    Keine Koordinatoren mit hinterlegter E-Mail-Adresse vorhanden.
    Füge E-Mail-Adressen in der <a href="/admin/coordinators">Koordinatorenverwaltung</a> hinzu.
</div>
<?php else: ?>

<?php if (!empty($without_email)): ?>
<div class="alert alert-warning mb-3">
    <strong><?= count($without_email) ?> Koordinator(en) ohne E-Mail:</strong>
    <ul class="mb-0 mt-1 small">
        <?php foreach ($without_email as $c): ?>
        <li><?= e($c['first_name'] . ' ' . $c['last_name']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="/admin/notify">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="subject" class="form-label">Betreff</label>
                <input type="text"
                       id="subject"
                       name="subject"
                       class="form-control"
                       maxlength="200"
                       required
                       value="<?= e($_POST['subject'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="body" class="form-label">Nachricht</label>
                <textarea id="body"
                          name="body"
                          class="form-control"
                          rows="6"
                          maxlength="2000"
                          required><?= e($_POST['body'] ?? '') ?></textarea>
            </div>
            <p class="text-muted small mb-3">
                Empfänger: <?= count($with_email) ?> Koordinator(en) mit E-Mail-Adresse
            </p>
            <button type="submit" class="btn btn-primary min-touch">
                <i class="bi bi-send me-1"></i>Nachricht senden
            </button>
        </form>
    </div>
</div>

<?php endif; ?>
