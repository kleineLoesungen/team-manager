<?php
// src/templates/coordinator/profile.php
// Variables (via use()): $self (array), $error (string), $success (bool), $is_confirm_route (bool), $is_first_confirm (bool), $calendar_token (string|null)
$action = $is_confirm_route ? '/coordinator/confirm-profile' : '/coordinator/profile';
$scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$ics_url    = $calendar_token ? ($scheme . '://' . $host . '/ics/' . $calendar_token . '.ics') : null;
?>

<?php if ($is_confirm_route && $is_first_confirm): ?>
<div class="alert alert-info d-flex gap-2 mb-4">
    <i class="bi bi-shield-check flex-shrink-0"></i>
    <div>
        <strong>Willkommen!</strong> Bitte überprüfe und bestätige deine Kontaktdaten, bevor du fortfährst.
        Deine Bestätigung ist nach §&nbsp;6 DSGVO für die Verarbeitung personenbezogener Daten
        durch den Verein erforderlich.
    </div>
</div>
<?php endif; ?>

<?php if ($success): render_flash('success', 'Deine Daten wurden gespeichert.'); endif; ?>
<?php if ($error): render_flash('error', $error); endif; ?>

<form method="POST" action="<?= $action ?>" novalidate>
    <?= csrf_field() ?>

    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold">Kontaktdaten</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6">
                    <label for="first_name" class="form-label">Vorname <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                           value="<?= e($self['first_name'] ?? '') ?>" maxlength="100" required>
                </div>
                <div class="col-6">
                    <label for="last_name" class="form-label">Nachname <span class="text-danger">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                           value="<?= e($self['last_name'] ?? '') ?>" maxlength="100" required>
                </div>
                <div class="col-12">
                    <label for="email" class="form-label">E-Mail <span class="text-muted small">(optional)</span></label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($self['email'] ?? '') ?>" maxlength="255"
                           placeholder="deine@email.de">
                </div>
                <div class="col-12">
                    <label for="phone" class="form-label">Telefon <span class="text-muted small">(optional)</span></label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="<?= e($self['phone'] ?? '') ?>" maxlength="50"
                           placeholder="+49 …">
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_confirm_route && $is_first_confirm): ?>
    <div class="card border-primary mb-4">
        <div class="card-body d-flex gap-2">
            <i class="bi bi-info-circle-fill text-primary flex-shrink-0 mt-1"></i>
            <p class="mb-0 small">
                Nach §&nbsp;6 Abs.&nbsp;1 lit.&nbsp;b DSGVO ist die Verarbeitung deiner Daten zur
                Erfüllung des Mitgliedschaftsverhältnisses zulässig. Du kannst deine Angaben jederzeit
                unter <strong>Mein Profil</strong> einsehen und ändern.
            </p>
        </div>
    </div>
    <button type="submit" class="btn btn-primary w-100 min-touch">
        <i class="bi bi-check-circle me-2"></i>Daten bestätigen &amp; fortfahren
    </button>
    <?php else: ?>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-floppy me-2"></i>Änderungen speichern
        </button>
        <a href="/coordinator/members" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>

    <?php if (!empty($self['confirmed_at'])): ?>
    <p class="text-muted small mt-3">
        <i class="bi bi-check-circle-fill text-success me-1"></i>
        Profil bestätigt am <?= e(date('d.m.Y', strtotime($self['confirmed_at']))) ?>.
    </p>
    <?php endif; ?>
    <?php endif; ?>
</form>

<?php if (!$is_confirm_route): ?>
<div class="card mt-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-calendar2-check"></i>
        <span class="fw-semibold">Kalender-Abo</span>
    </div>
    <div class="card-body">
        <?php if ($ics_url): ?>
        <p class="text-body-secondary small mb-3">
            Abonniere deinen persönlichen Kalender in Apple Kalender, Google Calendar oder Outlook.
            Der Link enthält alle sichtbaren Termine und Anwesenheitslisten.
        </p>
        <?php if (!empty($_GET['cal_reset'])): ?>
        <div class="alert alert-success py-2 small mb-3">
            <i class="bi bi-check-circle me-1"></i>Kalender-Link wurde erneuert. Bitte das Abo in deiner App aktualisieren.
        </div>
        <?php endif; ?>
        <div class="input-group mb-3">
            <input type="text" id="ics-url-coord" class="form-control form-control-sm font-monospace"
                   value="<?= e($ics_url) ?>" readonly>
            <button class="btn btn-outline-secondary btn-sm"
                    onclick="navigator.clipboard.writeText(document.getElementById('ics-url-coord').value).then(()=>{this.textContent='✓';setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard\'></i>';},1500)})"
                    type="button" title="Link kopieren">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>
        <a href="<?= e($ics_url) ?>" class="btn btn-sm btn-outline-primary min-touch me-2">
            <i class="bi bi-calendar-plus me-1"></i>In Kalender-App öffnen
        </a>
        <form method="POST" action="/coordinator/calendar-reset" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-danger min-touch"
                    onclick="return confirm('Link wirklich erneuern? Dein bisheriges Abo hört auf zu funktionieren.')">
                <i class="bi bi-arrow-clockwise me-1"></i>Link erneuern
            </button>
        </form>
        <?php else: ?>
        <p class="text-muted small mb-0">Kein Kalender-Link verfügbar. Bitte Seite neu laden.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="list-group mt-4">
    <a href="/coordinator/coordinators" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
        <i class="bi bi-person-badge"></i>
        <span class="flex-grow-1">Koordinatoren</span>
        <i class="bi bi-chevron-right text-muted small"></i>
    </a>
    <a href="/coordinator/settings" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
        <i class="bi bi-gear"></i>
        <span class="flex-grow-1">Einstellungen</span>
        <i class="bi bi-chevron-right text-muted small"></i>
    </a>
    <a href="/coordinator/logo" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
        <i class="bi bi-image"></i>
        <span class="flex-grow-1">Team-Logo</span>
        <i class="bi bi-chevron-right text-muted small"></i>
    </a>
    <?php if (!empty($_SESSION['coordinator_teams']) && count($_SESSION['coordinator_teams']) > 1): ?>
    <a href="/coordinator/switch-team" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
        <i class="bi bi-arrow-left-right"></i>
        <span class="flex-grow-1">Team wechseln</span>
        <i class="bi bi-chevron-right text-muted small"></i>
    </a>
    <?php endif; ?>
    <a href="/logout" class="list-group-item list-group-item-action d-flex align-items-center gap-3 text-danger">
        <i class="bi bi-box-arrow-right"></i>
        <span class="flex-grow-1">Abmelden</span>
    </a>
</div>
