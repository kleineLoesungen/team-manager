<?php
// src/templates/coordinator/member_profile_edit.php — Edit member profile form (coordinator)
// Variables: $profile (array), $clubs (array), $profile_id (int), $error (string)
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="mb-3">
    <a href="/coordinator/member-profiles/<?= (int)$profile_id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<form method="POST" action="/coordinator/member-profiles/<?= (int)$profile_id ?>/edit" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-6">
            <label for="first_name" class="form-label fw-semibold">Vorname <span class="text-danger">*</span></label>
            <input type="text" id="first_name" name="first_name" class="form-control min-touch"
                   value="<?= e($profile['first_name']) ?>" required autofocus>
        </div>
        <div class="col-6">
            <label for="last_name" class="form-label fw-semibold">Nachname <span class="text-danger">*</span></label>
            <input type="text" id="last_name" name="last_name" class="form-control min-touch"
                   value="<?= e($profile['last_name']) ?>" required>
        </div>
        <div class="col-12">
            <label for="email" class="form-label fw-semibold">E-Mail</label>
            <input type="email" id="email" name="email" class="form-control min-touch"
                   value="<?= e($profile['email'] ?? '') ?>" placeholder="optional">
        </div>
        <div class="col-12">
            <label for="phone" class="form-label fw-semibold">Telefon</label>
            <input type="text" id="phone" name="phone" class="form-control min-touch"
                   value="<?= e($profile['phone'] ?? '') ?>" placeholder="optional">
        </div>
        <?php if (!empty($clubs)): ?>
        <div class="col-12">
            <label for="club_id" class="form-label fw-semibold">Verein</label>
            <select id="club_id" name="club_id" class="form-select min-touch">
                <option value="0">— kein Verein —</option>
                <?php foreach ($clubs as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                    <?= ((int)($profile['club_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-12">
            <hr class="my-1">
            <p class="text-muted small mb-0">Kontaktperson (optional)</p>
        </div>
        <div class="col-12">
            <label for="contact_name" class="form-label fw-semibold">Kontaktname</label>
            <input type="text" id="contact_name" name="contact_name" class="form-control min-touch"
                   value="<?= e($profile['contact_name'] ?? '') ?>" placeholder="optional">
        </div>
        <div class="col-12">
            <label for="contact_phone" class="form-label fw-semibold">Kontakttelefon</label>
            <input type="text" id="contact_phone" name="contact_phone" class="form-control min-touch"
                   value="<?= e($profile['contact_phone'] ?? '') ?>" placeholder="optional">
        </div>
        <div class="col-12">
            <label for="contact_email" class="form-label fw-semibold">Kontakt-E-Mail</label>
            <input type="email" id="contact_email" name="contact_email" class="form-control min-touch"
                   value="<?= e($profile['contact_email'] ?? '') ?>" placeholder="optional">
        </div>
        <div class="col-12">
            <label for="description" class="form-label fw-semibold">Anmerkungen</label>
            <textarea id="description" name="description" class="form-control" rows="3"
                      placeholder="optional"><?= e($profile['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary min-touch">
                <i class="bi bi-check-lg me-1"></i>Speichern
            </button>
        </div>
    </div>
</form>

<div class="mt-4">
    <a href="/coordinator/member-profiles/<?= (int)$profile_id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>
