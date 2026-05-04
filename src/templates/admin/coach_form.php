<?php
// src/templates/admin/coach_form.php — Coach creation form
// Variables: $teams (array), $error (string), $selected_team_id (int|null)
?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<form method="POST" action="/admin/coaches/create">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="first_name" class="form-label fw-semibold small">Vorname</label>
        <input type="text"
               class="form-control min-touch"
               id="first_name"
               name="first_name"
               value="<?= e($_POST['first_name'] ?? '') ?>"
               required
               maxlength="100">
    </div>
    <div class="mb-3">
        <label for="last_name" class="form-label fw-semibold small">Nachname</label>
        <input type="text"
               class="form-control min-touch"
               id="last_name"
               name="last_name"
               value="<?= e($_POST['last_name'] ?? '') ?>"
               required
               maxlength="100">
    </div>
    <div class="mb-4">
        <label for="team_id" class="form-label fw-semibold small">Team</label>
        <select class="form-select min-touch" id="team_id" name="team_id" required>
            <option value="">— Team wählen —</option>
            <?php foreach ($teams as $t): ?>
            <option value="<?= e($t['id']) ?>"
                    <?= ((int)($selected_team_id ?? 0) === (int)$t['id']) ? 'selected' : '' ?>>
                <?= e($t['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/coaches" class="btn btn-outline-secondary">Abbrechen</a>
        <button type="submit" class="btn btn-primary">Moderator hinzufügen</button>
    </div>
</form>
