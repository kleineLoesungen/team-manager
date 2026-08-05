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

        <!-- My team: read-only display -->
        <div class="mb-3">
            <div class="small fw-medium text-muted mb-2">Mein Team</div>
            <?php if (!empty($my_linked)): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($my_linked as $u): ?>
                <span class="badge border py-1 px-2 d-inline-flex align-items-center gap-1
                             <?= $u['user_active'] ? 'bg-success-subtle text-success-emphasis border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle' ?>">
                    <i class="bi bi-person me-1"></i><?= e($u['username']) ?>
                    <?= $u['user_active'] ? '' : '<span class="opacity-75">(inaktiv)</span>' ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted small mb-0">Kein Account aus meinem Team verknüpft.</p>
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
    <h3 class="h6 fw-semibold mb-3">Attribute</h3>
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

<!-- Cross-team stats (column switcher + sum row) -->
<?php
$col_names = array_values(array_unique(array_column($cross_stats, 'col_name')));
$col_agg   = [];
foreach ($cross_stats as $stat) {
    $cn = $stat['col_name'];
    if (!isset($col_agg[$cn])) {
        $col_agg[$cn] = ['type' => $stat['data_type'], 'total' => 0, 'true_count' => 0, 'sum' => 0.0];
    }
    $col_agg[$cn]['total']++;
    if ($stat['data_type'] === 'boolean') {
        if (in_array($stat['value'], ['1', 'true'], true)) $col_agg[$cn]['true_count']++;
    } else {
        if ($stat['value'] !== '') $col_agg[$cn]['sum'] += (float)$stat['value'];
    }
}
?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Statistiken</div>
    <?php if (empty($cross_stats)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">
            <?= empty($my_linked) ? 'Kein Benutzeraccount verknüpft — keine Statistiken verfügbar.' : 'Noch keine Einsatzdaten vorhanden.' ?>
        </p>
    </div>
    <?php else: ?>
    <div class="card-body pb-0">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($col_names as $i => $cn): ?>
            <button type="button"
                    class="btn btn-sm <?= $i === 0 ? 'btn-primary' : 'btn-outline-secondary' ?> js-col-switch"
                    data-col="<?= e($cn) ?>">
                <?= e($cn) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Team</th>
                    <th>Datum</th>
                    <th>Wert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cross_stats as $stat): ?>
                <tr data-col="<?= e($stat['col_name']) ?>">
                    <td><?= e($stat['team_name']) ?></td>
                    <td><?= $stat['date'] ? e(date('d.m.Y', strtotime($stat['date']))) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?php if ($stat['data_type'] === 'boolean'): ?>
                        <?= in_array($stat['value'], ['1', 'true'], true)
                            ? '<i class="bi bi-check-circle-fill text-success"></i>'
                            : '<i class="bi bi-x-circle text-muted"></i>' ?>
                        <?php else: ?>
                        <?= e($stat['value']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($col_agg)): ?>
            <tfoot>
                <?php foreach ($col_agg as $cn => $agg): ?>
                <tr data-col="<?= e($cn) ?>" class="table-secondary fw-semibold">
                    <td colspan="2" class="text-muted small">Gesamt</td>
                    <td>
                        <?php if ($agg['type'] === 'boolean'): ?>
                        <?php $pct = $agg['total'] > 0 ? round($agg['true_count'] / $agg['total'] * 100) : 0; ?>
                        <?= $agg['true_count'] ?> / <?= $agg['total'] ?> <span class="text-muted">(<?= $pct ?>%)</span>
                        <?php else: ?>
                        <?= $agg['sum'] == (int)$agg['sum'] ? (int)$agg['sum'] : number_format($agg['sum'], 2, ',', '.') ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <script>
    (function () {
        var btns = document.querySelectorAll('.js-col-switch');
        var rows = document.querySelectorAll('tr[data-col]');
        function activate(col) {
            btns.forEach(function (b) {
                var active = b.dataset.col === col;
                b.classList.toggle('btn-primary', active);
                b.classList.toggle('btn-outline-secondary', !active);
            });
            rows.forEach(function (r) {
                r.style.display = r.dataset.col === col ? '' : 'none';
            });
        }
        if (btns.length > 0) activate(btns[0].dataset.col);
        btns.forEach(function (b) {
            b.addEventListener('click', function () { activate(this.dataset.col); });
        });
    }());
    </script>
    <?php endif; ?>
</div>

<?php if (!empty($my_linked)): ?>
<div class="card border-danger mb-4">
    <div class="card-header text-danger fw-semibold">Gefahrenzone</div>
    <div class="card-body">
        <?php foreach ($my_linked as $u): ?>
        <form method="POST"
              action="/coordinator/players/<?= (int)$player_id ?>/unlink-user"
              class="mb-2 last-mb-0">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <div class="small">
                    Verknüpfung mit <strong><?= e($u['username']) ?></strong> aufheben
                </div>
                <button type="submit"
                        class="btn btn-sm btn-outline-danger flex-shrink-0"
                        onclick="return confirm('<?= e('Verknüpfung mit ' . $u['username'] . ' wirklich aufheben?') ?>')">
                    <i class="bi bi-unlink me-1"></i>Trennen
                </button>
            </div>
        </form>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
