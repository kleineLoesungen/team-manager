<?php
// src/templates/coordinator/ticker_form.php — create ticker form
// Variables: $members (array), $form_values (array with keys: name, description, freigabe_members)
$freigabe_ids = $form_values['freigabe_members'] ?? [];
?>
<form method="POST" action="/coordinator/ticker/new">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="ticker_name" class="form-label fw-semibold">Ticker-Name <span class="text-danger">*</span></label>
        <input type="text" id="ticker_name" name="name" class="form-control"
               maxlength="255" required
               value="<?= e($form_values['name'] ?? '') ?>"
               placeholder='z.B. "Finale 2026"'>
        <div class="form-text">Erforderlich. Max. 255 Zeichen.</div>
    </div>

    <div class="mb-4">
        <label for="ticker_description" class="form-label">Beschreibung</label>
        <textarea id="ticker_description" name="description" class="form-control" rows="2"
                  placeholder='z.B. "Live-Ticker vom Turnier in München"'><?= e($form_values['description'] ?? '') ?></textarea>
        <div class="form-text">Optional. Kurze Info für die Besucher des Tickers.</div>
    </div>

    <?php if (!empty($members)): ?>
    <div class="mb-4">
        <label class="form-label fw-semibold">Mitglieder freigeben</label>
        <p class="form-text mt-0 mb-2">Folgende Mitglieder dürfen Nachrichten posten:</p>
        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
            <?php foreach ($members as $m): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       name="freigabe_members[]"
                       value="<?= (int)$m['id'] ?>"
                       id="m_<?= (int)$m['id'] ?>"
                       <?= in_array((int)$m['id'], $freigabe_ids, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="m_<?= (int)$m['id'] ?>">
                    <?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="form-text mt-1">Der Koordinator kann immer posten.</div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="/coordinator/ticker" class="btn btn-outline-secondary">Abbrechen</a>
    </div>
</form>
