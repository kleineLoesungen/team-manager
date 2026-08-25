<?php
// src/templates/member/player_profile.php — Member-facing player profile
declare(strict_types=1);
?>

<div class="mb-3">
    <a href="/member/profile" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Profil
    </a>
</div>

<?php if ($player === null): ?>
<!-- Not linked state: member has no linked player record -->
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-person-badge display-4 text-muted mb-3 d-block"></i>
        <p class="mb-1 fw-semibold">Kein Profil verknüpft</p>
        <p class="text-muted small mb-0">
            Dein Konto ist noch nicht mit einem Profil verknüpft.
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
        <?php
            $is_date  = ($attr['data_type'] ?? 'text') === 'date';
            $disp_val = $attr['value'];
            if ($is_date && $disp_val !== '') {
                try { $disp_val = (new DateTime($disp_val))->format('d.m.Y'); } catch (\Exception $ex) {}
            }
        ?>
        <div class="mb-3">
            <label class="form-label fw-medium mb-1"><?= e($attr['attr_name']) ?></label>
            <p class="form-control-plaintext py-0 mb-0 text-<?= $disp_val !== '' ? 'body' : 'muted' ?>">
                <?= $disp_val !== '' ? e($disp_val) : '—' ?>
            </p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Vergangene Einsätze: system columns with coordinator columns in "Weitere" dropdown -->
<?php
$col_names       = array_values(array_unique(array_column($system_stats, 'col_name')));
$col_names_coord = array_values(array_unique(array_column($coordinator_stats, 'col_name')));

$col_agg = [];
foreach ($system_stats as $stat) {
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
$col_agg_coord = [];
foreach ($coordinator_stats as $stat) {
    $cn = $stat['col_name'];
    if (!isset($col_agg_coord[$cn])) {
        $col_agg_coord[$cn] = ['type' => $stat['data_type'], 'total' => 0, 'true_count' => 0, 'sum' => 0.0];
    }
    $col_agg_coord[$cn]['total']++;
    if ($stat['data_type'] === 'boolean') {
        if (in_array($stat['value'], ['1', 'true'], true)) $col_agg_coord[$cn]['true_count']++;
    } else {
        if ($stat['value'] !== '') $col_agg_coord[$cn]['sum'] += (float)$stat['value'];
    }
}
$all_col_agg = array_merge($col_agg, $col_agg_coord);
?>
<div class="card mb-3">
    <div class="card-header fw-semibold">Vergangene Einsätze</div>
    <?php if (empty($system_stats) && empty($coordinator_stats)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">Noch keine Einsatzdaten vorhanden.</p>
    </div>
    <?php else: ?>
    <div class="card-body pb-0">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php foreach ($col_names as $i => $cn): ?>
            <button type="button"
                    class="btn btn-sm <?= $i === 0 ? 'btn-primary' : 'btn-outline-secondary' ?> js-col-switch"
                    data-col="<?= e($cn) ?>">
                <?= e($cn) ?>
            </button>
            <?php endforeach; ?>
            <?php if (!empty($col_names_coord)): ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    Weitere
                </button>
                <ul class="dropdown-menu">
                    <?php foreach ($col_names_coord as $cn): ?>
                    <li>
                        <button type="button" class="dropdown-item js-col-coord" data-col="<?= e($cn) ?>">
                            <?= e($cn) ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
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
                <?php foreach ($system_stats as $stat): ?>
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
                <?php foreach ($coordinator_stats as $stat): ?>
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
            <?php if (!empty($all_col_agg)): ?>
            <tfoot>
                <?php foreach ($all_col_agg as $cn => $agg): ?>
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
        var mainBtns = document.querySelectorAll('.js-col-switch');
        var rows     = document.querySelectorAll('tr[data-col]');

        function activate(col) {
            mainBtns.forEach(function (b) {
                var active = b.dataset.col === col;
                b.classList.toggle('btn-primary', active);
                b.classList.toggle('btn-outline-secondary', !active);
            });
            rows.forEach(function (r) {
                r.style.display = r.dataset.col === col ? '' : 'none';
            });
        }

        if (mainBtns.length > 0) activate(mainBtns[0].dataset.col);

        mainBtns.forEach(function (b) {
            b.addEventListener('click', function () { activate(this.dataset.col); });
        });
        document.querySelectorAll('.js-col-coord').forEach(function (item) {
            item.addEventListener('click', function () { activate(this.dataset.col); });
        });
    }());
    </script>
    <?php endif; ?>
</div>

<?php endif; ?>

<div class="mt-4">
    <a href="/member/profile" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Profil
    </a>
</div>
