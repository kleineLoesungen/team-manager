<?php
// src/templates/admin/player_form.php — Create player form
// Variables: $clubs (array), $teams (array), $error (string), $form (array)
?>
<div class="mb-3">
    <a href="/admin/players" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Spieler
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="/admin/players/create">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="first_name" class="form-label fw-semibold">Vorname <span class="text-danger">*</span></label>
        <input type="text" id="first_name" name="first_name" class="form-control"
               value="<?= e($form['first_name']) ?>" maxlength="100" required autofocus>
    </div>

    <div class="mb-3">
        <label for="last_name" class="form-label fw-semibold">Nachname <span class="text-danger">*</span></label>
        <input type="text" id="last_name" name="last_name" class="form-control"
               value="<?= e($form['last_name']) ?>" maxlength="100" required>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label fw-semibold">E-Mail <span class="text-muted fw-normal">(optional)</span></label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= e($form['email'] ?? '') ?>" maxlength="255"
               placeholder="spieler@beispiel.de">
    </div>

    <div class="mb-3">
        <label for="club_id" class="form-label fw-semibold">Klub <span class="text-muted fw-normal">(optional)</span></label>
        <select id="club_id" name="club_id" class="form-select">
            <option value="0">— kein Klub —</option>
            <?php foreach ($clubs as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$form['club_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="phone" class="form-label fw-semibold">Telefon <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" id="phone" name="phone" class="form-control"
               value="<?= e($form['phone']) ?>" maxlength="50">
    </div>

    <div class="mb-3">
        <label for="contact_name" class="form-label fw-semibold">Kontaktname <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" id="contact_name" name="contact_name" class="form-control"
               value="<?= e($form['contact_name']) ?>" maxlength="100"
               placeholder="z.B. Name eines Erziehungsberechtigten">
    </div>

    <div class="mb-3">
        <label for="contact_phone" class="form-label fw-semibold">Kontakttelefon <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" id="contact_phone" name="contact_phone" class="form-control"
               value="<?= e($form['contact_phone']) ?>" maxlength="50"
               placeholder="+49 …">
    </div>

    <div class="mb-3">
        <label for="contact_email" class="form-label fw-semibold">Kontakt-E-Mail <span class="text-muted fw-normal">(optional)</span></label>
        <input type="email" id="contact_email" name="contact_email" class="form-control"
               value="<?= e($form['contact_email']) ?>" maxlength="254"
               placeholder="eltern@beispiel.de">
    </div>

    <div class="mb-4">
        <label for="description" class="form-label fw-semibold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
        <textarea id="description" name="description" class="form-control" rows="3"
                  placeholder="Zusätzliche Informationen zum Spieler …"><?= e($form['description']) ?></textarea>
    </div>

    <div class="d-flex gap-3 align-items-center">
        <button type="submit" class="btn btn-primary min-touch">Spieler anlegen</button>
        <a href="/admin/players" class="btn btn-outline-secondary">Abbrechen</a>
    </div>
</form>

<div class="mt-4">
    <a href="/admin/players" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Spieler
    </a>
</div>
