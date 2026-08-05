<?php
// src/templates/coordinator/player_profile.php
declare(strict_types=1);

$active_teams = array_filter(
    array_merge($my_linked, $other_linked),
    fn($u) => $u['user_active'] && $u['team_active']
);
?>
<div class="mb-3">
    <a href="/coordinator/players" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zur Übersicht
    </a>
</div>

<?php if ($error):   ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<!-- Header -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <h2 class="card-title h5 fw-bold mb-1">
            <?= e($player['first_name'] . ' ' . $player['last_name']) ?>
        </h2>
        <?php if (!empty($player['club_name'])): ?>
        <div class="text-muted mb-2">
            <i class="bi bi-building me-1"></i><?= e($player['club_name']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($active_teams)): ?>
        <div class="mb-2 d-flex flex-wrap gap-1">
            <?php foreach ($active_teams as $u): ?>
            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                <i class="bi bi-people me-1"></i><?= e($u['team_name']) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($player['phone'])): ?>
        <div class="text-muted small mb-1">
            <i class="bi bi-telephone me-1"></i>
            <a href="tel:<?= e($player['phone']) ?>"><?= e($player['phone']) ?></a>
        </div>
        <?php endif; ?>
        <?php if (!empty($player['contact_name'])): ?>
        <div class="text-muted small mb-1">
            <i class="bi bi-person-lines-fill me-1"></i>
            Kontaktperson: <?= e($player['contact_name']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($player['description'])): ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Beschreibung</div>
    <div class="card-body"><p class="mb-0"><?= nl2br(e($player['description'])) ?></p></div>
</div>
<?php endif; ?>

<!-- User accounts -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Benutzerkonten</div>
    <div class="card-body">

        <!-- My team: editable -->
        <div class="mb-3">
            <div class="small fw-medium text-muted mb-2">Mein Team</div>
            <?php if (!empty($my_linked)): ?>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <?php foreach ($my_linked as $u): ?>
                <form method="POST" action="/coordinator/players/<?= (int)$player_id ?>/unlink-user" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                    <button type="submit"
                            class="btn btn-sm badge border py-1 px-2 d-inline-flex align-items-center gap-1
                                   <?= $u['user_active'] ? 'bg-success-subtle text-success-emphasis border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle' ?>"
                            onclick="return confirm('<?= e('Account ' . $u['username'] . ' vom Spieler trennen?') ?>')">
                        <i class="bi bi-person me-1"></i><?= e($u['username']) ?>
                        <?= $u['user_active'] ? '' : '<span class="opacity-75">(inaktiv)</span>' ?>
                        <i class="bi bi-x"></i>
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted small mb-2">Kein Account aus meinem Team verknüpft.</p>
            <?php endif; ?>

            <?php if (!empty($unlinked_my_members)): ?>
            <form method="POST" action="/coordinator/players/<?= (int)$player_id ?>/link-user"
                  class="d-flex align-items-center gap-2">
                <?= csrf_field() ?>
                <select name="user_id" class="form-select form-select-sm" style="max-width:220px">
                    <option value="">Account verknüpfen …</option>
                    <?php foreach ($unlinked_my_members as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"><?= e($m['username']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-link-45deg"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Other teams: read-only -->
        <?php if (!empty($other_linked)): ?>
        <div class="border-top pt-3">
            <div class="small fw-medium text-muted mb-2">Andere Teams</div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($other_linked as $u): ?>
                <?php
                    $active = $u['user_active'] && $u['team_active'];
                    $cls    = $active
                        ? 'bg-primary-subtle text-primary-emphasis border-primary-subtle'
                        : 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle';
                ?>
                <span class="badge border py-1 px-2 d-inline-flex align-items-center gap-1 <?= $cls ?>">
                    <i class="bi bi-person me-1"></i><?= e($u['username']) ?>
                    <span class="opacity-75">(<?= e($u['team_name']) ?><?= $active ? '' : ' – inaktiv' ?>)</span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Attributes -->
<?php if (!empty($attr_groups)): ?>
<form method="POST" action="/coordinator/players/<?= (int)$player_id ?>/attributes/save">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-semibold mb-0">Attribute</h3>
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-check-lg me-1"></i>Attribute speichern
        </button>
    </div>
    <?php foreach ($attr_groups as $group_name => $group): ?>
    <div class="card mb-3">
        <div class="card-header fw-semibold"><?= e($group_name) ?></div>
        <div class="card-body">
            <?php foreach ($group['attrs'] as $attr): ?>
            <div class="mb-3">
                <label class="form-label fw-medium mb-1">
                    <?= e($attr['attr_name']) ?>
                    <?php if (!$attr['visible_to_player']): ?>
                    <span class="badge bg-secondary ms-1" style="font-size:0.65rem">Nur Koordinator</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       name="values[<?= (int)$attr['attr_id'] ?>]"
                       value="<?= e($attr['value']) ?>"
                       placeholder="Kein Wert">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="mb-4">
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i>Alle Attribute speichern
        </button>
    </div>
</form>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Attribute</div>
    <div class="card-body">
        <p class="text-muted mb-0">
            Keine Attributgruppen konfiguriert.
            Der Admin kann Attribute unter Spielerattribute anlegen.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Statistics per team -->
<?php if (!empty($team_stats)): ?>
<h3 class="h6 fw-semibold mb-3">Statistiken</h3>
<?php foreach ($team_stats as $tid => $ts): ?>
<?php if (empty($ts['cols'])): continue; endif; ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e($ts['team_name']) ?></span>
        <?php if (!$ts['team_active']): ?>
        <span class="badge bg-secondary">Inaktiv</span>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <?php foreach ($ts['cols'] as $col): ?>
                    <th class="text-end text-nowrap">
                        <?= e($col['name']) ?>
                        <small class="text-muted fw-normal d-block">
                            <?= $col['data_type'] === 'number' ? 'Summe' : 'Anzahl' ?>
                        </small>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php foreach ($ts['cols'] as $col): ?>
                    <?php $cid = (int)$col['id']; $val = $ts['totals'][$cid] ?? 0; ?>
                    <td class="text-end fw-semibold">
                        <?= $col['data_type'] === 'number' ? ($val == (int)$val ? (int)$val : number_format((float)$val, 2, ',', '.')) : (int)$val ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Per-list detail (collapsible, current team only) -->
    <?php if ($ts['is_my_team'] && !empty($ts['lists'])): ?>
    <div class="card-footer p-0 border-top-0">
        <button class="btn btn-sm btn-link text-muted px-3 py-2 w-100 text-start"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#list-detail-<?= (int)$tid ?>">
            <i class="bi bi-chevron-down me-1"></i>Listendetails anzeigen
        </button>
        <div class="collapse" id="list-detail-<?= (int)$tid ?>">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Liste</th>
                            <th>Datum</th>
                            <?php foreach ($ts['cols'] as $col): ?>
                            <th class="text-end text-nowrap"><?= e($col['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ts['lists'] as $list): ?>
                        <?php $lid = (int)$list['id']; $has_data = isset($ts['cells'][$lid]); ?>
                        <?php if (!$has_data): continue; endif; ?>
                        <tr>
                            <td class="text-nowrap"><?= e($list['name']) ?></td>
                            <td class="text-nowrap text-muted small">
                                <?= $list['date'] ? e(date('d.m.Y', strtotime($list['date']))) : '—' ?>
                            </td>
                            <?php foreach ($ts['cols'] as $col): ?>
                            <?php $cid = (int)$col['id']; $v = $ts['cells'][$lid][$cid] ?? null; ?>
                            <td class="text-end">
                                <?php if ($v === null): ?>
                                <span class="text-muted">—</span>
                                <?php elseif ($col['data_type'] === 'boolean'): ?>
                                <?= in_array($v, ['1','true'], true)
                                    ? '<i class="bi bi-check-circle-fill text-success"></i>'
                                    : '<i class="bi bi-circle text-muted"></i>' ?>
                                <?php else: ?>
                                <?= e($v) ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php elseif (empty($my_linked)): ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Statistiken</div>
    <div class="card-body">
        <p class="text-muted mb-0">Kein Benutzeraccount verknüpft — keine Statistiken verfügbar.</p>
    </div>
</div>
<?php endif; ?>
