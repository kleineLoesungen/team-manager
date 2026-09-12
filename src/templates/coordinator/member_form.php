<?php
// src/templates/coordinator/member_form.php — New member creation form
// Variables (via use()): $error (string), $linkable_players (array)
// Two modes: link existing playerless record, or create new player inline.
?>
<div class="mb-3">
    <a href="/coordinator/members" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Mitglieder
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="/coordinator/members/create" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="create_mode" id="create_mode" value="new">

    <div class="col-12">
        <div class="d-flex gap-3 flex-wrap">
            <div class="form-check">
                <input type="radio" id="mode_new" name="_mode_radio" value="new" class="form-check-input" checked>
                <label for="mode_new" class="form-check-label fw-semibold">Neues Profil anlegen</label>
            </div>
            <div class="form-check">
                <input type="radio" id="mode_link" name="_mode_radio" value="link" class="form-check-input"
                       <?= empty($linkable_players) ? 'disabled' : '' ?>>
                <label for="mode_link" class="form-check-label <?= empty($linkable_players) ? 'text-muted' : 'fw-semibold' ?>">
                    Vorhandenes Profil verknüpfen
                    <?php if (empty($linkable_players)): ?>
                    <span class="small fw-normal">(keine verfügbar)</span>
                    <?php endif; ?>
                </label>
            </div>
        </div>
    </div>

    <!-- New player fields -->
    <div id="section_new" class="col-12 row g-3">
        <div class="col-12">
            <label for="first_name" class="form-label">Vorname <span class="text-danger">*</span></label>
            <input type="text"
                   class="form-control form-control-lg"
                   id="first_name"
                   name="first_name"
                   autocomplete="given-name"
                   value="<?= e($_POST['first_name'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label for="last_name" class="form-label">Nachname <span class="text-danger">*</span></label>
            <input type="text"
                   class="form-control form-control-lg"
                   id="last_name"
                   name="last_name"
                   autocomplete="family-name"
                   value="<?= e($_POST['last_name'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label for="email" class="form-label">E-Mail <span class="text-muted small">(optional)</span></label>
            <input type="email"
                   class="form-control form-control-lg"
                   id="email"
                   name="email"
                   autocomplete="email"
                   value="<?= e($_POST['email'] ?? '') ?>"
                   placeholder="spieler@email.de">
        </div>
    </div>

    <!-- Link existing profile fields -->
    <div id="section_link" class="col-12 d-none">
        <?php if (!empty($linkable_players)): ?>
        <label for="member_id_link" class="form-label">Profil auswählen</label>
        <select class="form-select form-select-lg" id="member_id_link" name="member_id_link">
            <option value="">— Profil wählen —</option>
            <?php foreach ($linkable_players as $p): ?>
            <option value="<?= (int)$p['id'] ?>"
                    <?= ((int)($_POST['member_id_link'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
                <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
                <?php if (!empty($p['club_name'])): ?> — <?= e($p['club_name']) ?><?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">
            <i class="bi bi-info-circle me-1"></i>
            Vorhandene Profile ohne Benutzerkonto.
        </div>
        <?php endif; ?>
    </div>

    <div class="col-12">
        <p class="text-muted small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Benutzername und Passwort werden automatisch generiert und einmalig angezeigt.
        </p>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-person-plus me-1"></i>Mitglied anlegen
        </button>
        <a href="/coordinator/members" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>

<script>
(function() {
    var modeInput   = document.getElementById('create_mode');
    var sectionNew  = document.getElementById('section_new');
    var sectionLink = document.getElementById('section_link');
    var radios      = document.querySelectorAll('[name="_mode_radio"]');
    var fnFirst     = document.getElementById('first_name');
    var fnLast      = document.getElementById('last_name');
    var fnLink      = document.getElementById('member_id_link');

    function switchMode(mode) {
        modeInput.value = mode;
        if (mode === 'link') {
            sectionNew.classList.add('d-none');
            sectionLink.classList.remove('d-none');
            fnFirst.removeAttribute('required');
            fnLast.removeAttribute('required');
            if (fnLink) fnLink.setAttribute('required', '');
        } else {
            sectionNew.classList.remove('d-none');
            sectionLink.classList.add('d-none');
            fnFirst.setAttribute('required', '');
            fnLast.setAttribute('required', '');
            if (fnLink) fnLink.removeAttribute('required');
        }
    }

    radios.forEach(function(r) {
        r.addEventListener('change', function() { switchMode(this.value); });
    });

    <?php if (($_POST['create_mode'] ?? '') === 'link'): ?>
    document.getElementById('mode_link').checked = true;
    switchMode('link');
    <?php endif; ?>
})();
</script>

<div class="mt-4">
    <a href="/coordinator/members" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Mitglieder
    </a>
</div>
