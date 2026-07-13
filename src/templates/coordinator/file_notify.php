<?php
// src/templates/coordinator/file_notify.php — Review page before sending file notification
// Variables: $file, $with_email, $without_email, $subject_prefilled, $content_link
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
            <?php
            $badge_class = match($file['visibility']) {
                'public'    => 'bg-success',
                'protected' => 'bg-warning text-dark',
                'private'   => 'bg-secondary',
                default     => 'bg-secondary',
            };
            $badge_label = match($file['visibility']) {
                'public'    => 'Öffentlich',
                'protected' => 'Geschützt',
                'private'   => 'Privat',
                default     => e($file['visibility']),
            };
            ?>
            <span class="badge <?= $badge_class ?>"><?= $badge_label ?></span>
        </p>
    </div>
</div>

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
               value="<?= e($subject_prefilled) ?>">
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

    <!-- 3. Mail-Vorschau -->
    <div class="card mb-3">
        <div class="card-header small fw-semibold">Vorschau der E-Mail</div>
        <div class="card-body">
            <p class="mb-1">
                <span class="text-muted small">An:</span> <?= count($with_email) ?> Empfänger
            </p>
            <p class="mb-1">
                <span class="text-muted small">Betreff:</span> <?= e($subject_prefilled) ?>
            </p>
            <hr class="my-2">
            <pre class="mb-0 small" style="white-space:pre-wrap; font-family:inherit;">Hallo {Vorname},

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
