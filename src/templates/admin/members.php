<?php
// src/templates/admin/players.php — Admin player list
// Variables: $profiles, $inactive_profiles, $clubs, $teams, $linked_users_map,
//            $unlinked_by_team, $has_unlinked, $search, $filter_club_id, $filter_team_id
$fmt_attr = function(array $a): string {
    $val = $a['value'];
    if (($a['data_type'] ?? 'text') === 'date' && $val !== '') {
        try { $val = (new DateTime($val))->format('d.m.Y'); } catch (\Exception $e) {}
    }
    return e($a['name']) . ': ' . e($val);
};
?>
<?php if (!empty($_GET['error'])): ?>
<?php render_flash('error', $_GET['error']); ?>
<?php endif; ?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Aktion erfolgreich.'); ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?= count($profiles) ?> aktive Mitglieder</span>
    <a href="/admin/members/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Mitglied hinzufügen
    </a>
</div>

<!-- Search + filter -->
<form method="GET" action="/admin/members" class="mb-4">
    <div class="row g-2">
        <div class="col-12">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control"
                       placeholder="Name suchen …"
                       value="<?= e($search) ?>"
                       autocomplete="off">
            </div>
        </div>
        <div class="col-6">
            <select name="club_id" class="form-select">
                <option value="0">Alle Klubs</option>
                <?php foreach ($clubs as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $filter_club_id === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4">
            <select name="team_id" class="form-select">
                <option value="0">Alle Teams</option>
                <?php foreach ($teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $filter_team_id === (int)$t['id'] ? 'selected' : '' ?>>
                    <?= e($t['name']) ?><?= $t['is_active'] ? '' : ' (inaktiv)' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-2">
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Filter</button>
        </div>
    </div>
    <?php if ($search !== '' || $filter_club_id > 0 || $filter_team_id > 0): ?>
    <div class="mt-1">
        <a href="/admin/members" class="small text-muted">Filter zurücksetzen</a>
    </div>
    <?php endif; ?>
</form>

<?php if ($has_unlinked): ?>
<script type="application/json" id="unlinked-by-team-data">
<?= json_encode($unlinked_by_team, JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
<?php endif; ?>

<!-- Mode switcher -->
<div class="d-flex gap-1 flex-wrap mb-3">
    <button class="btn btn-sm" data-mode-btn="club">Verein</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="contact">Kontakt</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="description">Beschreibung</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="attr-visible">Attribute (sichtbar)</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="attr-hidden">Attribute (verborgen)</button>
</div>

<?php if (empty($profiles)): ?>
<?php
$empty_action = ($search === '' && $filter_club_id === 0 && $filter_team_id === 0)
    ? '<a href="/admin/members/create" class="btn btn-outline-primary mt-3">Mitglied hinzufügen</a>'
    : null;
render_empty('person-vcard', 'Keine Mitglieder gefunden', 'Lege das erste Mitglied an, um loszulegen.', $empty_action);
?>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($profiles as $p): ?>
    <?php $linked = $linked_users_map[$p['id']] ?? []; ?>
    <div class="list-group-item">

        <!-- Name + switchable info -->
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold"><?= e($p['last_name']) ?>, <?= e($p['first_name']) ?></div>

                <div class="info-mode mt-1" data-mode="club">
                    <?php if (!empty($p['club_name'])): ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">
                        <i class="bi bi-building me-1"></i><?= e($p['club_name']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="contact">
                    <?php $has_contact = false; ?>
                    <?php if (!empty($p['email'])): $has_contact = true; ?>
                    <div class="text-muted small"><i class="bi bi-envelope me-1"></i><?= e($p['email']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($p['phone'])): $has_contact = true; ?>
                    <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= e($p['phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($p['contact_name']) || !empty($p['contact_phone']) || !empty($p['contact_email'])): $has_contact = true; ?>
                    <div class="text-muted small">
                        <i class="bi bi-person-lines-fill me-1"></i>
                        <?= e($p['contact_name'] ?? '') ?>
                        <?php if (!empty($p['contact_phone'])): ?><?php if (!empty($p['contact_name'])): ?>, <?php endif; ?><?= e($p['contact_phone']) ?><?php endif; ?>
                        <?php if (!empty($p['contact_email'])): ?><?php if (!empty($p['contact_name']) || !empty($p['contact_phone'])): ?>, <?php endif; ?><?= e($p['contact_email']) ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!$has_contact): ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="description">
                    <span class="text-muted small"><?= !empty($p['description']) ? e($p['description']) : '—' ?></span>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="attr-visible">
                    <?php $attrs = $player_attr_visible[$p['id']] ?? []; ?>
                    <?php if (!empty($attrs)): ?>
                    <span class="text-muted small"><?= implode(' · ', array_map($fmt_attr, $attrs)) ?></span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="attr-hidden">
                    <?php $attrs = $player_attr_hidden[$p['id']] ?? []; ?>
                    <?php if (!empty($attrs)): ?>
                    <span class="text-muted small"><?= implode(' · ', array_map($fmt_attr, $attrs)) ?></span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Linked user accounts -->
        <div class="mb-2">
            <div class="small fw-medium text-muted mb-1">Benutzerkonten</div>
            <?php if (!empty($linked)): ?>
            <div class="d-flex flex-wrap gap-1 align-items-center">
                <?php foreach ($linked as $u): ?>
                <?php
                    $team_ok  = !empty($u['team_active']);
                    $user_ok  = (bool)$u['is_active'];
                    $bg_class = ($team_ok && $user_ok) ? 'bg-success-subtle text-success-emphasis border-success-subtle'
                              : 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle';
                ?>
                <span class="badge border py-1 px-2 d-inline-flex align-items-center gap-1 <?= $bg_class ?>">
                    <i class="bi bi-person me-1"></i><?= e($u['username']) ?>
                    <?php if (!empty($u['team_name'])): ?>
                    <span class="opacity-75">(<?= e($u['team_name']) ?><?= $team_ok ? '' : ' – inaktiv' ?>)</span>
                    <?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <span class="text-muted small">Kein Account verknüpft</span>
            <?php endif; ?>
        </div>

        <!-- Add link: team → user two-step -->
        <?php if ($has_unlinked): ?>
        <form method="POST" action="/admin/members/<?= (int)$p['id'] ?>/link-user"
              class="d-flex align-items-center gap-2 mb-2 js-link-form">
            <?= csrf_field() ?>
            <select class="form-select js-team-pick">
                <option value="">Team …</option>
                <?php foreach ($unlinked_by_team as $tid => $tdata): ?>
                <option value="<?= (int)$tid ?>">
                    <?= e($tdata['team_name']) ?><?= $tdata['team_active'] ? '' : ' (inaktiv)' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="user_id" class="form-select js-user-pick" disabled>
                <option value="">Mitglied …</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary" disabled>
                <i class="bi bi-link-45deg"></i>
            </button>
        </form>
        <?php endif; ?>

        <!-- Bearbeiten + Deaktivieren -->
        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
            <a href="/admin/members/<?= (int)$p['id'] ?>/edit" data-save-scroll
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Bearbeiten
            </a>
            <form method="POST" action="/admin/members/<?= (int)$p['id'] ?>/deactivate">
                <?= csrf_field() ?>
                <input type="hidden" name="confirm_deactivate" value="1">
                <button type="submit" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pause-circle me-1"></i>Deaktivieren
                </button>
            </form>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($inactive_profiles)): ?>
<div class="mt-4">
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            type="button" data-bs-toggle="collapse" data-bs-target="#inactivePlayers" aria-expanded="false">
        <i class="bi bi-chevron-down"></i>
        Inaktiv (<?= count($inactive_profiles) ?>)
    </button>
    <div class="collapse mt-2" id="inactivePlayers">
    <div class="list-group opacity-75">
        <?php foreach ($inactive_profiles as $p): ?>
        <?php $linked = $linked_users_map[$p['id']] ?? []; ?>
        <div class="list-group-item opacity-75">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="fw-semibold text-muted d-flex align-items-center gap-1">
                        <?= e($p['last_name']) ?>, <?= e($p['first_name']) ?>
                        <?php render_badge('dim', 'Inaktiv'); ?>
                    </div>
                    <?php if (!empty($p['club_name'])): ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle mt-1">
                        <i class="bi bi-building me-1"></i><?= e($p['club_name']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Linked user accounts -->
            <?php if (!empty($linked)): ?>
            <div class="mb-2">
                <div class="d-flex flex-wrap gap-1 align-items-center">
                    <?php foreach ($linked as $u): ?>
                    <?php
                        $team_ok  = !empty($u['team_active']);
                        $user_ok  = (bool)$u['is_active'];
                        $bg_class = ($team_ok && $user_ok) ? 'bg-success-subtle text-success-emphasis border-success-subtle'
                                  : 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle';
                    ?>
                    <span class="badge border py-1 px-2 d-inline-flex align-items-center gap-1 <?= $bg_class ?>">
                        <i class="bi bi-person me-1"></i><?= e($u['username']) ?>
                        <?php if (!empty($u['team_name'])): ?>
                        <span class="opacity-75">(<?= e($u['team_name']) ?>)</span>
                        <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reaktivieren + Bearbeiten + Löschen -->
            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                <a href="/admin/members/<?= (int)$p['id'] ?>/edit"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Bearbeiten
                </a>
                <form method="POST" action="/admin/members/<?= (int)$p['id'] ?>/reactivate">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                    </button>
                </form>
                <?php if (empty($linked)): ?>
                <form method="POST" action="/admin/members/<?= (int)$p['id'] ?>/delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="confirm_delete" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>Löschen
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var STORAGE_KEY = 'admin-members-info-mode';
    var current = sessionStorage.getItem(STORAGE_KEY) || 'club';

    function setMode(mode) {
        current = mode;
        sessionStorage.setItem(STORAGE_KEY, mode);
        document.querySelectorAll('[data-mode-btn]').forEach(function (btn) {
            var on = btn.dataset.modeBtn === mode;
            btn.className = 'btn btn-sm ' + (on ? 'btn-primary' : 'btn-outline-secondary');
        });
        document.querySelectorAll('.info-mode').forEach(function (el) {
            el.classList.toggle('d-none', el.dataset.mode !== mode);
        });
    }

    document.querySelectorAll('[data-mode-btn]').forEach(function (btn) {
        btn.addEventListener('click', function () { setMode(this.dataset.modeBtn); });
    });

    setMode(current);
}());
</script>

<?php if ($has_unlinked): ?>
<script>
(function () {
    var data = JSON.parse(document.getElementById('unlinked-by-team-data').textContent);

    document.querySelectorAll('.js-link-form').forEach(function (form) {
        var teamSel = form.querySelector('.js-team-pick');
        var userSel = form.querySelector('.js-user-pick');
        var btn     = form.querySelector('button[type="submit"]');

        teamSel.addEventListener('change', function () {
            var tid = this.value;
            userSel.innerHTML = '<option value="">Mitglied …</option>';
            userSel.disabled  = true;
            btn.disabled      = true;

            if (tid && data[tid]) {
                data[tid].members.forEach(function (m) {
                    var opt       = document.createElement('option');
                    opt.value     = m.id;
                    opt.textContent = m.username + ' — ' + m.last_name + ', ' + m.first_name;
                    userSel.appendChild(opt);
                });
                userSel.disabled = false;
            }
        });

        userSel.addEventListener('change', function () {
            btn.disabled = this.value === '';
        });
    });
}());
</script>
<?php endif; ?>
