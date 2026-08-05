<?php
// src/templates/member/player_profile.php — Member-facing player profile
declare(strict_types=1);
?>

<?php if ($player === null): ?>
<!-- Not linked state: member has no linked player record -->
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-person-badge display-4 text-muted mb-3 d-block"></i>
        <p class="mb-1 fw-semibold">Kein Spielerprofil verknüpft</p>
        <p class="text-muted small mb-0">
            Dein Konto ist noch nicht mit einem Spielerprofil verknüpft.
            Bitte wende dich an deinen Koordinator.
        </p>
    </div>
</div>

<?php else: ?>

<!-- Player header card -->
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
        <?php if (!empty($player['description'])): ?>
        <p class="text-muted small mt-2 mb-0"><?= nl2br(e($player['description'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Team accounts -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Teamzugehörigkeit</div>
    <?php if (empty($history)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">Kein Benutzeraccount in einem Team verknüpft.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Team</th>
                    <th>Benutzername</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e($h['team_name']) ?></td>
                    <td><code class="small"><?= e($h['username']) ?></code></td>
                    <td>
                        <?php if ($h['team_active'] && $h['is_active']): ?>
                        <span class="badge bg-success">Aktiv</span>
                        <?php elseif (!$h['team_active']): ?>
                        <span class="badge bg-secondary">Team inaktiv</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">Konto inaktiv</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Attribute groups (visible_to_player only) -->
<?php if (!empty($attr_groups)): ?>
<div class="mb-2">
    <h3 class="h6 fw-semibold">Meine Attribute</h3>
</div>
<?php foreach ($attr_groups as $group_name => $group): ?>
<div class="card mb-3">
    <div class="card-header fw-semibold"><?= e($group_name) ?></div>
    <div class="card-body">
        <?php foreach ($group['attrs'] as $attr): ?>
        <div class="mb-3">
            <label class="form-label fw-medium mb-1"><?= e($attr['attr_name']) ?></label>
            <?php if ($attr['editable_by_player']): ?>
            <!-- TODO: member-editable attribute save is not in scope for this phase; shown read-only -->
            <p class="form-control-plaintext py-0 mb-0 text-<?= $attr['value'] !== '' ? 'body' : 'muted' ?>">
                <?= $attr['value'] !== '' ? e($attr['value']) : '—' ?>
            </p>
            <?php else: ?>
            <p class="form-control-plaintext py-0 mb-0 text-<?= $attr['value'] !== '' ? 'body' : 'muted' ?>">
                <?= $attr['value'] !== '' ? e($attr['value']) : '—' ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Cross-team stats -->
<?php
$col_names = array_values(array_unique(array_column($cross_stats, 'col_name')));

// Per-column aggregates for the summary row
$col_agg = [];
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
    <div class="card-header fw-semibold">Vergangene Einsätze</div>
    <?php if (empty($cross_stats)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">Noch keine Einsatzdaten vorhanden.</p>
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

<?php endif; ?>
