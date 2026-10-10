<?php
// src/templates/components/push_notify_form.php — Push form of a notification page (Issue #11)
// Variables: $notify_action (POST target), $back_url, $with_push (recipients with a device),
//            $without_push (recipients without), $push_title_prefilled, $selected_ids (null = all),
//            $is_private (only coordinators receive it)
// Text with a live preview; who has no device is listed below, like missing e-mail addresses.
$p_title = (string)($_POST['push_title'] ?? $push_title_prefilled);
$p_body  = (string)($_POST['push_body'] ?? '');
?>
<form method="POST" action="<?= e($notify_action) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="channel" value="push">

    <div class="alert alert-info small mb-3">
        <i class="bi bi-phone me-1" aria-hidden="true"></i>
        Push erreicht nur, wer Push-Benachrichtigungen im Profil eingeschaltet hat — auf dem iPhone
        nur in der installierten App (App auf dem Startbildschirm). Ein Tipp auf die Nachricht öffnet den Inhalt.
    </div>

    <div class="mb-3">
        <label for="push_title" class="form-label">Titel</label>
        <input type="text" id="push_title" name="push_title" class="form-control" required
               maxlength="<?= NOTIFY_PUSH_TITLE_MAX ?>" value="<?= e($p_title) ?>" data-push-preview="title">
    </div>
    <div class="mb-3">
        <label for="push_body" class="form-label">Push-Text</label>
        <textarea id="push_body" name="push_body" class="form-control" rows="3" required
                  maxlength="<?= NOTIFY_PUSH_BODY_MAX ?>" data-push-preview="body"><?= e($p_body) ?></textarea>
        <div class="form-text"><span data-push-count><?= mb_strlen($p_body) ?></span> / <?= NOTIFY_PUSH_BODY_MAX ?> Zeichen — kurz halten, mehr zeigt der Sperrbildschirm nicht.</div>
    </div>

    <?php render_recipient_picker($with_push, $selected_ids); ?>

    <!-- Vorschau, wie die Nachricht auf dem Sperrbildschirm aussieht -->
    <div class="card mb-3">
        <div class="card-header small fw-semibold">Vorschau</div>
        <div class="card-body d-flex gap-3 align-items-start">
            <img src="/icons/icon-192.png" alt="" width="36" height="36" class="rounded">
            <div class="min-w-0">
                <div class="fw-semibold text-break" data-push-show="title"><?= e($p_title) ?></div>
                <div class="small text-break" data-push-show="body"><?= $p_body !== '' ? e($p_body) : '<span class="text-muted">(Dein Push-Text erscheint hier)</span>' ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($without_push)): ?>
    <div class="alert alert-info mb-3">
        <strong>Nicht per Push erreichbar:</strong>
        <ul class="mb-0 mt-1 small">
            <?php foreach ($without_push as $u): ?>
            <li><?= e($u['first_name'] . ' ' . $u['last_name']) ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="mb-0 mt-1 small text-muted">Diese Personen haben Push auf keinem Gerät eingeschaltet. Erreiche sie per E-Mail.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($is_private)): ?>
    <div class="alert alert-warning mb-3">
        <i class="bi bi-lock me-1"></i>
        Dieser Inhalt ist <strong>privat</strong> — nur Koordinatoren erhalten die Benachrichtigung.
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch" <?= $with_push ? '' : 'disabled' ?>>
            <i class="bi bi-send me-1"></i>Push senden
        </button>
        <a href="<?= e($back_url) ?>" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>

<script>
// Vorschau und Zeichenzähler live
(function () {
    document.querySelectorAll('[data-push-preview]').forEach(function (field) {
        field.addEventListener('input', function () {
            var key = field.getAttribute('data-push-preview');
            var out = document.querySelector('[data-push-show="' + key + '"]');
            out.textContent = field.value || (key === 'body' ? '(Dein Push-Text erscheint hier)' : '');
            if (key === 'body') document.querySelector('[data-push-count]').textContent = field.value.length;
        });
    });
})();
</script>
