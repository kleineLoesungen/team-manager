<?php
// src/templates/member/confirm_profile.php
// Variables (via use()): $player (array|null), $clubs (array), $error (string), $is_first_confirm (bool)
?>
<?php if (isset($_GET['success'])): render_flash('success', 'Gespeichert.'); endif; ?>
<?php if ($error): render_flash('error', $error); endif; ?>

<?php if ($is_first_confirm): ?>
<div class="alert alert-info d-flex gap-2 mb-4">
    <i class="bi bi-shield-check flex-shrink-0 fs-5"></i>
    <div>
        <strong>Willkommen!</strong> Bitte überprüfe und bestätige deine Daten, bevor du fortfährst.
        Deine Bestätigung ist nach §&nbsp;6 DSGVO für die Verarbeitung personenbezogener Daten
        durch den Verein erforderlich.
    </div>
</div>
<?php endif; ?>

<?php if ($player): ?>
<form method="POST" action="/member/confirm-profile" novalidate>
    <?= csrf_field() ?>

    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold">Persönliche Daten</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6">
                    <label for="first_name" class="form-label">Vorname <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                           value="<?= e($player['first_name']) ?>" maxlength="100" required>
                </div>
                <div class="col-6">
                    <label for="last_name" class="form-label">Nachname <span class="text-danger">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                           value="<?= e($player['last_name']) ?>" maxlength="100" required>
                </div>
                <div class="col-12">
                    <label for="email" class="form-label">E-Mail <span class="text-muted small">(optional)</span></label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($player['email'] ?? '') ?>" maxlength="255"
                           placeholder="deine@email.de">
                </div>
                <div class="col-12">
                    <label for="phone" class="form-label">Telefon <span class="text-muted small">(optional)</span></label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="<?= e($player['phone'] ?? '') ?>" maxlength="50"
                           placeholder="+49 …">
                </div>
                <?php if (!empty($clubs)): ?>
                <div class="col-12">
                    <label for="club_id" class="form-label">Verein <span class="text-muted small">(optional)</span></label>
                    <select id="club_id" name="club_id" class="form-select">
                        <option value="0">— keinen auswählen —</option>
                        <?php foreach ($clubs as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>"
                            <?= ((int)($player['club_id'] ?? 0) === (int)$cl['id']) ? 'selected' : '' ?>>
                            <?= e($cl['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold">Kontakt <span class="text-muted fw-normal small">(optional)</span></span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label for="contact_name" class="form-label">Kontaktname</label>
                    <input type="text" id="contact_name" name="contact_name" class="form-control"
                           value="<?= e($player['contact_name'] ?? '') ?>" maxlength="100"
                           placeholder="z. B. Elternteil / Partner">
                </div>
                <div class="col-12">
                    <label for="contact_phone" class="form-label">Kontakttelefon</label>
                    <input type="text" id="contact_phone" name="contact_phone" class="form-control"
                           value="<?= e($player['contact_phone'] ?? '') ?>" maxlength="50"
                           placeholder="+49 …">
                </div>
                <div class="col-12">
                    <label for="contact_email" class="form-label">Kontakt-E-Mail</label>
                    <input type="email" id="contact_email" name="contact_email" class="form-control"
                           value="<?= e($player['contact_email'] ?? '') ?>" maxlength="254"
                           placeholder="eltern@beispiel.de">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold">Weitere Informationen <span class="text-muted fw-normal small">(optional)</span></span>
        </div>
        <div class="card-body">
            <div class="mb-0">
                <label for="description" class="form-label">Anmerkungen</label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="Allergien, Besonderheiten …"><?= e($player['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <?php if ($is_first_confirm): ?>
    <div class="card border-primary mb-4">
        <div class="card-body d-flex gap-2">
            <i class="bi bi-info-circle-fill text-primary flex-shrink-0 fs-5 mt-1"></i>
            <p class="mb-0 small">
                Nach §&nbsp;6 Abs.&nbsp;1 lit.&nbsp;b DSGVO ist die Verarbeitung deiner Daten zur
                Erfüllung des Mitgliedschaftsverhältnisses zulässig. Du kannst deine Angaben jederzeit
                unter <strong>Mein Profil</strong> einsehen und ändern. Eine Abmeldung ist beim
                Koordinator deines Teams möglich.
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
        <a href="/member/lists" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
    <?php endif; ?>
</form>
<?php else: ?>
<!-- No linked player — just stamp confirmation so member can proceed -->
<?php
$_confirm_action = '<form method="POST" action="/member/confirm-profile">'
    . csrf_field()
    . '<button type="submit" class="btn btn-outline-primary mt-3 min-touch">Trotzdem fortfahren</button>'
    . '</form>';
render_empty('person-x', 'Kein Mitgliedsprofil',
    'Dein Konto ist noch keinem Mitgliedsprofil zugeordnet. Bitte wende dich an deinen Koordinator, um dein Mitgliedsprofil zu verknüpfen.',
    $_confirm_action
);
?>
<?php endif; ?>
