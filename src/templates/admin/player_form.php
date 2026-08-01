<?php
// src/templates/admin/player_form.php — Create player form
// Variables: $clubs (array), $teams (array), $error (string), $form (array)
?>
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
        <label for="club_id" class="form-label fw-semibold">Klub <span class="text-danger">*</span></label>
        <select id="club_id" name="club_id" class="form-select" required>
            <option value="0">Klub wählen …</option>
            <?php foreach ($clubs as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$form['club_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="team_id" class="form-label fw-semibold">Team <span class="text-muted fw-normal">(optional)</span></label>
        <select id="team_id" name="team_id" class="form-select">
            <option value="0">Kein Team</option>
            <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (int)$form['team_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                <?= e($t['name']) ?>
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
