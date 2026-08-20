<?php
// src/templates/coordinator/member_change_player.php
// Variables: $member, $current_player_id, $linkable_players, $error
?>
<div class="mb-3">
    <a href="<?= e($cancel_url) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header fw-semibold">Aktuelles Spielerprofil</div>
    <div class="card-body">
        <div class="fw-semibold"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></div>
        <?php if (!empty($member['player_email'])): ?>
        <div class="text-muted small"><i class="bi bi-envelope me-1"></i><?= e($member['player_email']) ?></div>
        <?php endif; ?>
        <a href="/coordinator/players/<?= (int)$current_player_id ?>" class="btn btn-sm btn-outline-secondary mt-2">
            <i class="bi bi-person-vcard me-1"></i>Profil ansehen
        </a>
    </div>
</div>

<form method="POST" action="/coordinator/members/<?= (int)$member['id'] ?>/change-player"
      class="row g-3" style="max-width:520px;">
    <?= csrf_field() ?>
    <input type="hidden" name="create_mode" id="create_mode" value="link">

    <div class="col-12">
        <div class="d-flex gap-3 flex-wrap">
            <div class="form-check">
                <input type="radio" id="mode_new" name="_mode_radio" value="new" class="form-check-input"
                       <?= empty($linkable_players) ? 'checked' : '' ?>>
                <label for="mode_new" class="form-check-label fw-semibold">Neuen Spieler anlegen</label>
            </div>
            <div class="form-check">
                <input type="radio" id="mode_link" name="_mode_radio" value="link" class="form-check-input"
                       <?= !empty($linkable_players) ? 'checked' : 'disabled' ?>>
                <label for="mode_link" class="form-check-label <?= empty($linkable_players) ? 'text-muted' : 'fw-semibold' ?>">
                    Vorhandenen Spieler wählen
                    <?php if (empty($linkable_players)): ?>
                    <span class="small fw-normal">(keine verfügbar)</span>
                    <?php endif; ?>
                </label>
            </div>
        </div>
    </div>

    <!-- Link existing player -->
    <div id="section_link" class="col-12">
        <label for="player_id_link" class="form-label">Spieler auswählen</label>
        <?php if (!empty($linkable_players)): ?>
        <select class="form-select" id="player_id_link" name="player_id_link">
            <option value="">— Spieler wählen —</option>
            <?php foreach ($linkable_players as $p): ?>
            <option value="<?= (int)$p['id'] ?>"
                    <?= ((int)($_POST['player_id_link'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
                <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
                <?php if (!empty($p['club_name'])): ?> — <?= e($p['club_name']) ?><?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Spielerprofile ohne Benutzerkonto</div>
        <?php else: ?>
        <p class="text-muted small">Keine weiteren Spieler verfügbar. Lege einen neuen an.</p>
        <?php endif; ?>
    </div>

    <!-- New player fields -->
    <div id="section_new" class="col-12 row g-3" style="display:none">
        <div class="col-12">
            <label for="first_name" class="form-label">Vorname <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="first_name" name="first_name"
                   value="<?= e($_POST['first_name'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label for="last_name" class="form-label">Nachname <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="last_name" name="last_name"
                   value="<?= e($_POST['last_name'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label for="email" class="form-label">E-Mail <span class="text-muted small">(optional)</span></label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= e($_POST['email'] ?? '') ?>" placeholder="spieler@email.de">
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-link-45deg me-1"></i>Verknüpfung ändern
        </button>
        <a href="<?= e($cancel_url) ?>" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>

<script>
(function () {
    var modeInput   = document.getElementById('create_mode');
    var sectionLink = document.getElementById('section_link');
    var sectionNew  = document.getElementById('section_new');
    var radios      = document.querySelectorAll('[name="_mode_radio"]');
    var fnFirst     = document.getElementById('first_name');
    var fnLast      = document.getElementById('last_name');
    var fnLink      = document.getElementById('player_id_link');

    function switchMode(mode) {
        modeInput.value = mode;
        if (mode === 'new') {
            sectionLink.style.display = 'none';
            sectionNew.style.display  = '';
            fnFirst.setAttribute('required', '');
            fnLast.setAttribute('required', '');
            if (fnLink) fnLink.removeAttribute('required');
        } else {
            sectionLink.style.display = '';
            sectionNew.style.display  = 'none';
            fnFirst.removeAttribute('required');
            fnLast.removeAttribute('required');
            if (fnLink) fnLink.setAttribute('required', '');
        }
    }

    radios.forEach(function (r) {
        r.addEventListener('change', function () { switchMode(this.value); });
    });

    <?php
    $restore_mode = ($_POST['create_mode'] ?? '') ?: (empty($linkable_players) ? 'new' : 'link');
    ?>
    switchMode('<?= $restore_mode === 'new' ? 'new' : 'link' ?>');
}());
</script>

<div class="mt-4">
    <a href="<?= e($cancel_url) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>
