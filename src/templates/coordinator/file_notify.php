<?php
// src/templates/coordinator/file_notify.php — Review page before sending file notification
// Variables: $file, $with_email, $without_email, $subject_prefilled, $content_link, $selected_ids
// Per UI spec Screen 2b: same structure as list_notify.php, "Diese Datei" in private warning.
?>

<!-- Back link -->
<div class="mb-3">
    <a href="/coordinator/files/<?= (int)$file['id'] ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zur Datei
    </a>
</div>

<!-- 1. Kontext-Karte -->
<div class="card mb-3">
    <div class="card-body">
        <p class="mb-1 small text-muted">Inhalt</p>
        <p class="mb-0 fw-medium"><?= e($file['name']) ?></p>
        <p class="mb-0 small text-muted mt-1">
            <?php render_badge(
                match($file['visibility']) {
                    'public'    => 'ok',
                    'protected' => 'warn',
                    'private'   => 'dim',
                    default     => 'dim',
                },
                match($file['visibility']) {
                    'public'    => 'Öffentlich',
                    'protected' => 'Geschützt',
                    'private'   => 'Privat',
                    default     => htmlspecialchars($file['visibility'], ENT_QUOTES),
                }
            ); ?>
        </p>
    </div>
</div>

<!-- Kanal: E-Mail oder Push (Issue #11) -->
<?php render_notify_channel_tabs('/coordinator/files/' . (int)$file['id'] . '/notify', $channel); ?>

<?php if ($channel === 'push'):
    $notify_action        = '/coordinator/files/' . (int)$file['id'] . '/notify';
    $back_url             = '/coordinator/files/' . (int)$file['id'];
    $push_title_prefilled = mb_substr(preg_replace('/^\[(.+?)\] /u', '$1 - ', $subject_prefilled), 0, NOTIFY_PUSH_TITLE_MAX);   // „U11 - Training“
    $is_private           = $file['visibility'] === 'private';
    require ROOT_PATH . '/src/templates/components/push_notify_form.php';
else: ?>
<!-- 2+3. Formular + Vorschau -->
<form method="POST" action="/coordinator/files/<?= (int)$file['id'] ?>/notify">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="subject" class="form-label">Betreff</label>
        <input type="text"
               id="subject"
               name="subject"
               class="form-control"
               maxlength="200"
               required
               value="<?= e($_POST['subject'] ?? $subject_prefilled) ?>">
    </div>

    <div class="mb-3">
        <label for="body" class="form-label">Deine Nachricht an die Empfänger</label>
        <textarea id="body"
                  name="body"
                  class="form-control"
                  rows="5"
                  maxlength="2000"
                  required><?= e($_POST['body'] ?? '') ?></textarea>
    </div>

    <!-- Empfänger einzeln abwählbar -->
    <?php render_recipient_picker($with_email, $selected_ids); ?>

    <!-- 3. Mail-Vorschau -->
    <?php
    $both_count = count(array_filter($with_email,
        fn($u) => !empty($u['email']) && !empty($u['contact_email'])));
    ?>
    <div class="card mb-3">
        <div class="card-header small fw-semibold">Vorschau der E-Mail</div>
        <div class="card-body">
            <p class="mb-1">
                <span class="text-muted small">An:</span> die ausgewählten Empfänger (<?= count($with_email) ?> möglich)
                <?php if ($both_count > 0): ?>
                <span class="text-muted small">(<?= $both_count ?> davon erhalten auch eine Kopie an die Kontakt-E-Mail)</span>
                <?php endif; ?>
            </p>
            <p class="mb-1">
                <span class="text-muted small">Betreff:</span> <?= e($subject_prefilled) ?>
            </p>
            <hr class="my-2">
            <pre class="mb-0 small">Hallo {Vorname},

(Deine Nachricht erscheint hier)

---

<?= e($file['name']) ?>

Link: <?= e($content_link) ?></pre>
        </div>
    </div>

    <!-- 4. Empfänger-Hinweis (nur wenn Personen fehlen) -->
    <?php if (!empty($without_email)): ?>
    <div class="alert alert-info mb-3">
        <strong>Kein Zugang per E-Mail:</strong>
        <ul class="mb-0 mt-1 small">
            <?php foreach ($without_email as $u): ?>
            <li><?= e($u['first_name'] . ' ' . $u['last_name']) ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="mb-0 mt-1 small text-muted">
            Diese Personen erhalten keine Benachrichtigung, weil keine E-Mail-Adresse hinterlegt ist.
        </p>
    </div>
    <?php endif; ?>

    <!-- 5. Sichtbarkeits-Warnung (nur bei private) -->
    <?php if ($file['visibility'] === 'private'): ?>
    <div class="alert alert-warning mb-3">
        <i class="bi bi-lock me-1"></i>
        Diese Datei ist <strong>privat</strong> — nur Koordinatoren erhalten die Benachrichtigung.
        Mitglieder können den Link nicht öffnen.
    </div>
    <?php endif; ?>

    <!-- 6. Senden-Button -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-send me-1"></i>Jetzt senden
        </button>
        <a href="/coordinator/files/<?= (int)$file['id'] ?>"
           class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>

</form>
<?php endif; ?>
