<?php
// src/templates/admin/member_edit.php — Edit member profile form
// Variables: $profile (array), $clubs (array), $error (string)
?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Änderungen gespeichert.'); ?>
<?php endif; ?>
<?php if (!empty($error)): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>

<div class="mb-3 d-flex align-items-center gap-2">
    <a href="/admin/members" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
    <?php if (!$profile['is_active']): ?>
    <?php render_badge('dim', 'Deaktiviert'); ?>
    <form method="POST" action="/admin/members/<?= (int)$profile['id'] ?>/reactivate">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-success">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
        </button>
    </form>
    <?php endif; ?>
</div>

<form method="POST" action="/admin/members/<?= (int)$profile['id'] ?>/edit">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-6">
            <label for="first_name" class="form-label fw-semibold">Vorname <span class="text-danger">*</span></label>
            <input type="text"
                   id="first_name"
                   name="first_name"
                   class="form-control min-touch"
                   value="<?= e($profile['first_name']) ?>"
                   required
                   autofocus>
        </div>
        <div class="col-6">
            <label for="last_name" class="form-label fw-semibold">Nachname <span class="text-danger">*</span></label>
            <input type="text"
                   id="last_name"
                   name="last_name"
                   class="form-control min-touch"
                   value="<?= e($profile['last_name']) ?>"
                   required>
        </div>
        <div class="col-12">
            <label for="club_id" class="form-label fw-semibold">Klub</label>
            <select id="club_id" name="club_id" class="form-select min-touch">
                <option value="0">— kein Klub —</option>
                <?php foreach ($clubs as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                    <?= (int)$profile['club_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label for="email" class="form-label fw-semibold">E-Mail</label>
            <input type="email"
                   id="email"
                   name="email"
                   class="form-control min-touch"
                   value="<?= e($profile['email'] ?? '') ?>"
                   placeholder="optional">
        </div>
        <div class="col-12">
            <label for="phone" class="form-label fw-semibold">Telefon</label>
            <input type="text"
                   id="phone"
                   name="phone"
                   class="form-control min-touch"
                   value="<?= e($profile['phone'] ?? '') ?>"
                   placeholder="optional">
        </div>

        <div class="col-12">
            <hr class="my-1">
            <p class="text-muted small mb-2">Kontaktperson (optional)</p>
        </div>
        <div class="col-12">
            <label for="contact_name" class="form-label fw-semibold">Kontaktname</label>
            <input type="text"
                   id="contact_name"
                   name="contact_name"
                   class="form-control min-touch"
                   value="<?= e($profile['contact_name'] ?? '') ?>"
                   placeholder="optional">
        </div>
        <div class="col-12">
            <label for="contact_phone" class="form-label fw-semibold">Kontakttelefon</label>
            <input type="text"
                   id="contact_phone"
                   name="contact_phone"
                   class="form-control min-touch"
                   value="<?= e($profile['contact_phone'] ?? '') ?>"
                   placeholder="optional">
        </div>
        <div class="col-12">
            <label for="contact_email" class="form-label fw-semibold">Kontakt-E-Mail</label>
            <input type="email"
                   id="contact_email"
                   name="contact_email"
                   class="form-control min-touch"
                   value="<?= e($profile['contact_email'] ?? '') ?>"
                   placeholder="optional">
        </div>
        <div class="col-12">
            <label for="description" class="form-label fw-semibold">Anmerkungen</label>
            <textarea id="description"
                      name="description"
                      class="form-control"
                      rows="3"
                      placeholder="optional"><?= e($profile['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary min-touch">
                <i class="bi bi-check-lg me-1"></i>Änderungen speichern
            </button>
        </div>
    </div>
</form>
