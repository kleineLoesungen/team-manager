<?php
// src/templates/member/profile.php — Full player data edit page
// Variables (via use()): $player (array|null), $clubs (array), $attr_groups (array), $error (string), $success (bool)
?>

<?php if ($error): ?>
<div class="alert alert-danger mb-3"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success mb-3">Deine Daten wurden gespeichert.</div>
<?php endif; ?>

<?php if ($player): ?>
<form method="POST" action="/member/profile" novalidate>
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
            <label for="description" class="form-label">Anmerkungen</label>
            <textarea id="description" name="description" class="form-control" rows="3"
                      placeholder="Allergien, Besonderheiten …"><?= e($player['description'] ?? '') ?></textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary min-touch">
        <i class="bi bi-floppy me-2"></i>Änderungen speichern
    </button>
</form>

<?php $has_editable = false;
foreach ($attr_groups as $g) {
    foreach ($g['attrs'] as $a) { if ($a['editable_by_player']) { $has_editable = true; break 2; } }
}
?>

<?php if (!empty($attr_groups)): ?>
<?= $has_editable ? '<form method="POST" action="/member/profile/attributes/save">' : '' ?>
<?= $has_editable ? csrf_field() : '' ?>

<h3 class="h6 fw-semibold mt-4 mb-3">Meine Attribute</h3>
<?php foreach ($attr_groups as $gname => $group): ?>
<div class="card mb-3">
    <div class="card-header fw-semibold"><?= e($gname) ?></div>
    <div class="card-body">
        <?php foreach ($group['attrs'] as $attr): ?>
        <div class="mb-3">
            <label class="form-label fw-medium mb-1"><?= e($attr['attr_name']) ?></label>
            <?php if ($attr['editable_by_player']): ?>
            <input type="text" class="form-control"
                   name="values[<?= (int)$attr['attr_id'] ?>]"
                   value="<?= e($attr['value']) ?>">
            <?php else: ?>
            <p class="form-control-plaintext py-0 mb-0 <?= $attr['value'] !== '' ? '' : 'text-muted' ?>">
                <?= $attr['value'] !== '' ? e($attr['value']) : '—' ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($has_editable): ?>
<button type="submit" class="btn btn-primary min-touch">
    <i class="bi bi-floppy me-2"></i>Attribute speichern
</button>
</form>
<?php endif; ?>
<?php endif; ?>

<?php else: ?>
<div class="card mb-4">
    <div class="card-body text-center py-5">
        <i class="bi bi-person-x display-4 text-muted mb-3 d-block"></i>
        <p class="mb-1">Dein Konto ist noch keinem Mitgliedsprofil zugeordnet.</p>
        <p class="text-muted small">Bitte wende dich an deinen Koordinator.</p>
    </div>
</div>
<?php endif; ?>

<div class="list-group mt-4">
    <a href="/member/coordinators" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
        <i class="bi bi-person-badge fs-5"></i>
        <span class="flex-grow-1">Koordinatoren</span>
        <i class="bi bi-chevron-right text-muted small"></i>
    </a>
    <a href="/member/member-profile" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
        <i class="bi bi-clock-history fs-5"></i>
        <span class="flex-grow-1">Verlauf</span>
        <i class="bi bi-chevron-right text-muted small"></i>
    </a>
    <a href="/logout" class="list-group-item list-group-item-action d-flex align-items-center gap-3 text-danger">
        <i class="bi bi-box-arrow-right fs-5"></i>
        <span class="flex-grow-1">Abmelden</span>
    </a>
</div>
