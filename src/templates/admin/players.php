<?php
// src/templates/admin/players.php — Admin player list
// Variables: $players, $clubs, $teams, $linked_users_map, $unlinked_members,
//            $search, $filter_club_id, $filter_team_id
?>
<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['success'])): ?>
<div class="alert alert-success"><?= e($_GET['success']) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?= count($players) ?> Spieler</span>
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

<?php if (empty($players)): ?>
<div class="alert alert-info">
    Keine Spieler gefunden.
    <?php if ($search === '' && $filter_club_id === 0 && $filter_team_id === 0): ?>
    <a href="/admin/players/create" class="alert-link">Ersten Spieler anlegen</a>.
    <?php endif; ?>
</div>
<?php else: ?>
<div class="list-group">
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
                <?php if (!empty($p['phone'])): ?>
                <div class="text-muted small mt-1"><?= e($p['phone']) ?></div>
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
                    $team_ok   = !empty($u['team_active']);
                    $user_ok   = (bool)$u['is_active'];
                    $bg_class  = ($team_ok && $user_ok) ? 'bg-success-subtle text-success-emphasis border-success-subtle'
                               : 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle';
                ?>
                <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/unlink-user" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button type="submit"
                            class="btn btn-sm badge border py-1 px-2 d-inline-flex align-items-center gap-1 <?= $bg_class ?>"
                            onclick="return confirm('<?= e('Account ' . $u['username'] . ' vom Spieler trennen?') ?>')">
                        <i class="bi bi-person me-1"></i><?= e($u['username']) ?>
                        <?php if (!empty($u['team_name'])): ?>
                        <span class="opacity-75">(<?= e($u['team_name']) ?><?= $team_ok ? '' : ' – inaktiv' ?>)</span>
                        <?php endif; ?>
                        <i class="bi bi-x"></i>
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <span class="text-muted small">Kein Account verknüpft</span>
            <?php endif; ?>
        </div>

        <!-- Add link form -->
        <?php if (!empty($unlinked_members)): ?>
        <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/link-user"
              class="d-flex align-items-center gap-2 mb-2">
            <?= csrf_field() ?>
            <select name="user_id" class="form-select form-select-sm" style="max-width:240px">
                <option value="">— Account verknüpfen —</option>
                <?php foreach ($unlinked_members as $m): ?>
                <option value="<?= (int)$m['id'] ?>">
                    <?= e($m['username']) ?> — <?= e($m['last_name'] . ', ' . $m['first_name']) ?>
                    <?= !empty($m['team_name']) ? '(' . e($m['team_name']) . ($m['team_active'] ? '' : ' – inaktiv') . ')' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-link-45deg"></i>
            </button>
        </form>
        <?php endif; ?>

        <!-- Edit accordion -->
        <div class="accordion accordion-flush" id="acc-<?= (int)$p['id'] ?>">
            <div class="accordion-item border-0">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed p-0 bg-transparent text-secondary small fw-normal shadow-none"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#edit-<?= (int)$p['id'] ?>">
                        <i class="bi bi-pencil me-1"></i>Bearbeiten
                    </button>
                </h3>
                <div id="edit-<?= (int)$p['id'] ?>" class="accordion-collapse collapse">
                    <div class="accordion-body px-0 pt-2 pb-0">
                        <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/edit">
                            <?= csrf_field() ?>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <input type="text" name="first_name" class="form-control form-control-sm"
                                           value="<?= e($p['first_name']) ?>" placeholder="Vorname" required>
                                </div>
                                <div class="col-6">
                                    <input type="text" name="last_name" class="form-control form-control-sm"
                                           value="<?= e($p['last_name']) ?>" placeholder="Nachname" required>
                                </div>
                                <div class="col-12">
                                    <select name="club_id" class="form-select form-select-sm" required>
                                        <option value="0">Klub wählen …</option>
                                        <?php foreach ($clubs as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>"
                                            <?= (int)$p['club_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="text" name="phone" class="form-control form-control-sm"
                                           value="<?= e($p['phone'] ?? '') ?>" placeholder="Telefon (optional)">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="contact_name" class="form-control form-control-sm"
                                           value="<?= e($p['contact_name'] ?? '') ?>" placeholder="Kontaktname (opt.)">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Speichern</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
