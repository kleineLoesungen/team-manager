<?php
// src/templates/player/list_detail.php — List table for player
// Variables: $list (with visibility + show_all_rows), $columns, $players, $cells, $current_user_id
// edit button only shown for public lists + own row; protected = read-only
?>

<div class="d-flex justify-content-between align-items-center mb-1">
    <a href="/player/lists" class="text-muted small">
        <i class="bi bi-arrow-left me-1"></i>Alle Listen
    </a>
    <?php if (!empty($list['date'])): ?>
    <span class="text-muted small"><?= e((new DateTime($list['date']))->format('d.m.Y')) ?></span>
    <?php endif; ?>
</div>
<?php if (!empty($list['description'])): ?>
<p class="text-muted small mb-3"><?= e($list['description']) ?></p>
<?php endif; ?>

<?php if (empty($columns)): ?>
<div class="alert alert-info">Diese Liste hat noch keine Spalten.</div>
<?php elseif (empty($players)): ?>
<div class="text-center py-5"><p class="text-muted">Keine Mitglieder im Team.</p></div>
<?php else: ?>

<?php $can_edit = $list['visibility'] === 'public'; ?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
            <tr>
                <?php if ($list['show_all_rows']): ?>
                <th class="text-nowrap">Mitglied</th>
                <?php endif; ?>
                <?php foreach ($columns as $col): ?>
                <th class="text-nowrap"><?= e($col['name']) ?></th>
                <?php endforeach; ?>
                <?php if ($can_edit): ?><th></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($players as $player): ?>
            <?php $is_own_row = (int)$player['id'] === $current_user_id; ?>
            <tr <?= $is_own_row ? 'class="table-primary"' : '' ?>>
                <?php if ($list['show_all_rows']): ?>
                <td class="text-nowrap fw-medium">
                    <?= e($player['first_name'] . ' ' . $player['last_name']) ?>
                    <?php if ($is_own_row): ?>
                    <span class="badge bg-primary ms-1 small">Ich</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <?php foreach ($columns as $col): ?>
                <td>
                    <?php
                        $val = $cells[(int)$player['id']][(int)$col['id']] ?? null;
                        if ($val === null || $val === '') {
                            echo '';
                        } elseif ($col['data_type'] === 'boolean') {
                            echo $val === '1'
                                ? '<i class="bi bi-check-lg text-success"></i>'
                                : '<i class="bi bi-x-lg text-muted"></i>';
                        } else {
                            echo e($val);
                        }
                    ?>
                </td>
                <?php endforeach; ?>
                <?php if ($can_edit): ?>
                <td>
                    <?php if ($is_own_row): ?>
                    <a href="/player/lists/<?= (int)$list['id'] ?>/rows/<?= (int)$player['id'] ?>/edit"
                       class="btn btn-sm btn-outline-primary min-touch">
                        Bearbeiten
                    </a>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php
            // Build totals per column over visible $players
            $col_totals = [];
            foreach ($columns as $col) {
                $cid = (int)$col['id'];
                if ($col['data_type'] === 'number') {
                    $sum = 0;
                    foreach ($players as $player) {
                        $v = $cells[(int)$player['id']][$cid] ?? null;
                        if ($v !== null && $v !== '' && is_numeric($v)) {
                            $sum += (float)$v;
                        }
                    }
                    $col_totals[$cid] = ($sum == floor($sum))
                        ? (int)$sum
                        : number_format($sum, 2, ',', '.');
                } elseif ($col['data_type'] === 'boolean') {
                    $count = 0;
                    foreach ($players as $player) {
                        if (($cells[(int)$player['id']][$cid] ?? null) === '1') {
                            $count++;
                        }
                    }
                    $col_totals[$cid] = $count;
                } else {
                    $col_totals[$cid] = '';
                }
            }
        ?>
        <tfoot class="table-light">
            <tr>
                <?php if ($list['show_all_rows']): ?>
                <td class="text-nowrap fw-bold">Gesamt</td>
                <?php endif; ?>
                <?php foreach ($columns as $col): ?>
                <td class="text-nowrap fw-bold"><?= $col_totals[(int)$col['id']] ?></td>
                <?php endforeach; ?>
                <?php if ($can_edit): ?><td></td><?php endif; ?>
            </tr>
        </tfoot>
    </table>
</div>

<?php endif; ?>
