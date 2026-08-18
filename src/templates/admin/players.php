<?php
// src/templates/admin/players.php — Admin player list
// Variables: $players, $inactive_players, $clubs, $teams, $linked_users_map,
//            $unlinked_by_team, $has_unlinked, $search, $filter_club_id, $filter_team_id
?>
<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['success'])): ?>
<div class="alert alert-success"><?= e($_GET['success']) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?= count($players) ?> aktive Spieler</span>
    <a href="/admin/players/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Spieler hinzufügen
    </a>
</div>

<!-- Search + filter -->
<form method="GET" action="/admin/players" class="mb-4">
    <div class="row g-2">
        <div class="col-12">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control"
                       placeholder="Name suchen …"
                       value="<?= e($search) ?>"
                       autocomplete="off">
            </div>
        </div>
        <div class="col-6">
            <select name="club_id" class="form-select form-select-sm">
                <option value="0">Alle Klubs</option>
                <?php foreach ($clubs as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $filter_club_id === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4">
            <select name="team_id" class="form-select form-select-sm">
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
        <a href="/admin/players" class="small text-muted">Filter zurücksetzen</a>
    </div>
    <?php endif; ?>
</form>

<?php if ($has_unlinked): ?>
<script type="application/json" id="unlinked-by-team-data">
<?= json_encode($unlinked_by_team, JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
<?php endif; ?>

<?php if (empty($players)): ?>
<div class="alert alert-info">
    Keine aktiven Spieler gefunden.
    <?php if ($search === '' && $filter_club_id === 0 && $filter_team_id === 0): ?>
    <a href="/admin/players/create" class="alert-link">Ersten Spieler anlegen</a>.
    <?php endif; ?>
</div>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($players as $p): ?>
    <?php $linked = $linked_users_map[$p['id']] ?? []; ?>
    <div class="list-group-item px-3 py-3">

        <!-- Name + club -->
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
                <div class="fw-semibold"><?= e($p['last_name']) ?>, <?= e($p['first_name']) ?></div>
                <?php if (!empty($p['club_name'])): ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle mt-1">
                    <i class="bi bi-building me-1"></i><?= e($p['club_name']) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($p['email'])): ?>
                <div class="text-muted small mt-1"><i class="bi bi-envelope me-1"></i><?= e($p['email']) ?></div>
                <?php endif; ?>
                <?php if (!empty($p['phone'])): ?>
                <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= e($p['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($p['contact_name']) || !empty($p['contact_phone']) || !empty($p['contact_email'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-person-lines-fill me-1"></i>
                    Kontakt: <?= e($p['contact_name'] ?? '') ?>
                    <?php if (!empty($p['contact_phone'])): ?>
                    <?php if (!empty($p['contact_name'])): ?>, <?php endif; ?>
                    <?= e($p['contact_phone']) ?>
                    <?php endif; ?>
                    <?php if (!empty($p['contact_email'])): ?>
                    <?php if (!empty($p['contact_name']) || !empty($p['contact_phone'])): ?>, <?php endif; ?>
                    <?= e($p['contact_email']) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['description'])): ?>
                <div class="text-muted small fst-italic mt-1"><?= e($p['description']) ?></div>
                <?php endif; ?>
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
        <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/link-user"
              class="d-flex align-items-center gap-2 mb-2 js-link-form">
            <?= csrf_field() ?>
            <select class="form-select form-select-sm js-team-pick" style="max-width:150px">
                <option value="">Team …</option>
                <?php foreach ($unlinked_by_team as $tid => $tdata): ?>
                <option value="<?= (int)$tid ?>">
                    <?= e($tdata['team_name']) ?><?= $tdata['team_active'] ? '' : ' (inaktiv)' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="user_id" class="form-select form-select-sm js-user-pick"
                    style="max-width:180px" disabled>
                <option value="">Mitglied …</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary" disabled>
                <i class="bi bi-link-45deg"></i>
            </button>
        </form>
        <?php endif; ?>

        <!-- Bearbeiten + Deaktivieren -->
        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
            <a href="/admin/players/<?= (int)$p['id'] ?>/edit" data-save-scroll
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Bearbeiten
            </a>
            <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/deactivate"
                  onsubmit="return confirm('Spieler deaktivieren?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pause-circle me-1"></i>Deaktivieren
                </button>
            </form>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($inactive_players)): ?>
<details class="mb-4">
    <summary class="text-muted small mb-2" style="cursor:pointer">
        <?= count($inactive_players) ?> deaktivierte Spieler anzeigen
    </summary>
    <div class="list-group mt-2">
        <?php foreach ($inactive_players as $p): ?>
        <?php $linked = $linked_users_map[$p['id']] ?? []; ?>
        <div class="list-group-item px-3 py-3 opacity-75">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="fw-semibold text-muted">
                        <?= e($p['last_name']) ?>, <?= e($p['first_name']) ?>
                        <span class="badge bg-secondary ms-1">Inaktiv</span>
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

            <!-- Reaktivieren + Bearbeiten -->
            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                <a href="/admin/players/<?= (int)$p['id'] ?>/edit"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Bearbeiten
                </a>
                <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/reactivate">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

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
