<?php
// src/templates/player/list_detail.php — List table for player (D-14, CELL-04)
// All players visible as rows. Only own row has edit button (other rows read-only).
// Variables: $list, $columns, $players, $cells, $current_user_id (int, from session)
?>

<div class="mb-3">
    <a href="/player/lists" class="text-muted small">
        <i class="bi bi-arrow-left me-1"></i>Alle Listen
    </a>
</div>

<?php if (empty($columns)): ?>
<div class="alert alert-info">Diese Liste hat noch keine Spalten.</div>
<?php elseif (empty($players)): ?>
<div class="text-center py-5"><p class="text-muted">Keine Spieler im Team.</p></div>
<?php else: ?>

<!-- Per D-04: Bootstrap table-responsive for mobile horizontal scroll -->
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap">Spieler</th>
                <?php foreach ($columns as $col): ?>
                <th class="text-nowrap"><?= e($col['name']) ?></th>
                <?php endforeach; ?>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($players as $player): ?>
            <?php $is_own_row = (int)$player['id'] === $current_user_id; ?>
            <tr <?= $is_own_row ? 'class="table-primary"' : '' ?>>
                <td class="text-nowrap fw-medium">
                    <?= e($player['first_name'] . ' ' . $player['last_name']) ?>
                    <?php if ($is_own_row): ?>
                    <span class="badge bg-primary ms-1 small">Ich</span>
                    <?php endif; ?>
                </td>
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
                <td>
                    <?php if ($is_own_row): ?>
                    <!-- CELL-04 + CELL-01: only own row gets edit button -->
                    <a href="/player/lists/<?= (int)$list['id'] ?>/rows/<?= (int)$player['id'] ?>/edit"
                       class="btn btn-sm btn-outline-primary min-touch">
                        Bearbeiten
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
