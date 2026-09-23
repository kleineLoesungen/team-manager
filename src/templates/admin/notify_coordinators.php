<?php
// src/templates/admin/notify_coordinators.php — Admin notify coordinators page
// Variables: $with_email (array), $without_email (array), $error (string), $success (string)
// Per UI spec Screen 4.
?>

<?php render_page_header('Koordinatoren benachrichtigen', '/admin/coordinators'); ?>

<?php if ($error): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>

<?php if ($success): ?>
<?php render_flash('success', $success); ?>
<?php endif; ?>

<?php if (empty($with_email)): ?>
<?php render_empty('envelope-x', 'Keine E-Mail-Adressen hinterlegt',
    'Koordinatoren haben noch keine E-Mail-Adresse eingetragen.',
    '<a href="/admin/coordinators" class="btn btn-outline-primary mt-3">Zu Koordinatoren</a>'); ?>
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
