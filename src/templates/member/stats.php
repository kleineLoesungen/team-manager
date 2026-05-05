<?php // src/templates/player/stats.php — Player statistics: own row only (STAT-01, D-04, D-05) ?>

<?php if (empty($global_columns)): ?>
    <div class="alert alert-info">
        Dein Koordinator hat noch keine globalen Spalten definiert. Sobald globale Spalten angelegt sind, erscheinen hier deine Statistiken.
    </div>
<?php else: ?>

    <p class="text-muted mb-4 small">
        Statistiken werden aus allen öffentlichen und geschützten Listen berechnet.
        Die Zeitfenster zeigen Werte der letzten 4, 4–8 und 8–12 Wochen (nur Listen mit Datum).
    </p>

    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Spalte</th>
                    <th class="text-end text-nowrap">Gesamt</th>
                    <th class="text-end text-nowrap">Letzte&nbsp;4&nbsp;Wo.</th>
                    <th class="text-end text-nowrap">4–8&nbsp;Wo.</th>
                    <th class="text-end text-nowrap">8–12&nbsp;Wo.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($global_columns as $col): ?>
                    <?php $vals = $player_stats[(int)$col['id']] ?? ['all' => 0, '4w' => 0, '4_8w' => 0, '8_12w' => 0]; ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= e($col['name']) ?></td>
                        <?php foreach (['all', '4w', '4_8w', '8_12w'] as $win): ?>
                            <?php
                                $v = (float)($vals[$win] ?? 0);
                                echo '<td class="text-end text-nowrap fw-semibold">';
                                echo ($v == floor($v)) ? (int)$v : number_format($v, 2, ',', '.');
                                echo '</td>';
                            ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($per_list_rows)): ?>
    <h5 class="mb-3 mt-4">Listenübersicht</h5>
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Liste</th>
                    <th class="text-nowrap text-muted small fw-normal">Datum</th>
                    <?php foreach ($global_columns as $col): ?>
                        <th class="text-end text-nowrap"><?= e($col['name']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($per_list_rows as $list_row): ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= e($list_row['name']) ?></td>
                        <td class="text-nowrap text-muted small">
                            <?= $list_row['date'] ? date('d.m.Y', strtotime($list_row['date'])) : '—' ?>
                        </td>
                        <?php foreach ($global_columns as $col): ?>
                            <td class="text-end text-nowrap">
                                <?php
                                    $cid = (int)$col['id'];
                                    $lid = (int)$list_row['id'];
                                    $val = $per_list_cells[$lid][$cid] ?? null;
                                    if ($val === null) {
                                        echo '<span class="text-muted">—</span>';
                                    } elseif ($col['data_type'] === 'boolean') {
                                        echo in_array($val, ['1', 'true'], true) ? '✓' : '✗';
                                    } else {
                                        $n = (float)$val;
                                        echo ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.');
                                    }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="fw-bold">
                <tr>
                    <td colspan="2">Gesamt</td>
                    <?php foreach ($global_columns as $col): ?>
                        <td class="text-end text-nowrap">
                            <?php
                                $cid    = (int)$col['id'];
                                $totals = $per_list_totals[$cid] ?? null;
                                if ($col['data_type'] === 'number') {
                                    $n = (float)($totals['sum'] ?? 0);
                                    echo ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.');
                                } else {
                                    $count_true  = (int)($totals['count_true'] ?? 0);
                                    $total_lists = (int)($col_list_counts[$cid] ?? 0);
                                    $pct = $total_lists > 0 ? round($count_true / $total_lists * 100) : 0;
                                    echo $count_true . ' <small class="text-muted fw-normal">(' . $pct . '%)</small>';
                                }
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

<?php endif; ?>
