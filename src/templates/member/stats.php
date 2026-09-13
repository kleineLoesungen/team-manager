<?php // src/templates/member/stats.php — Member statistics: own row only (STAT-01) ?>
<?php if (isset($_GET['success'])): render_flash('success', 'Gespeichert.'); endif; ?>

<?php if (empty($global_columns)): ?>
    <div class="alert alert-info">
        Dein Koordinator hat noch keine globalen Spalten definiert. Sobald globale Spalten angelegt sind, erscheinen hier deine Statistiken.
    </div>
<?php else: ?>

    <p class="text-muted mb-4 small">
        Statistiken werden aus allen öffentlichen und geschützten Listen berechnet.
        Die Zeitfenster zeigen Werte der letzten 4, 4–8 und 8–12 Wochen (nur Listen mit Datum).
    </p>

    <?php render_matrix_table(
        ['Spalte', 'Gesamt', 'Letzte 4 Wo.', '4–8 Wo.', '8–12 Wo.'],
        function() use ($global_columns, $player_stats) {
            foreach ($global_columns as $col):
                $vals = $player_stats[(int)$col['id']] ?? ['all' => 0, '4w' => 0, '4_8w' => 0, '8_12w' => 0];
            ?>
                <tr>
                    <td class="fw-semibold text-nowrap"><?= e($col['name']) ?></td>
                    <?php foreach (['all', '4w', '4_8w', '8_12w'] as $win):
                        $v = (float)($vals[$win] ?? 0);
                    ?>
                    <td class="text-end text-nowrap fw-semibold">
                        <?= ($v == floor($v)) ? (int)$v : number_format($v, 2, ',', '.') ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach;
        }
    ); ?>

    <?php if (!empty($per_list_rows)): ?>
    <h5 class="mb-3 mt-4">Listenübersicht</h5>
    <?php
    // Build headers for per-list table
    $_perlist_headers = ['Liste', 'Datum'];
    foreach ($global_columns as $_col) {
        $_perlist_headers[] = $_col['name'];
    }
    ?>
    <?php render_matrix_table(
        $_perlist_headers,
        function() use ($per_list_rows, $global_columns, $per_list_cells) {
            foreach ($per_list_rows as $list_row):
            ?>
                <tr>
                    <td class="fw-semibold text-nowrap"><?= e($list_row['name']) ?></td>
                    <td class="text-nowrap text-muted small">
                        <?= $list_row['date'] ? date('d.m.Y', strtotime($list_row['date'])) : '—' ?>
                    </td>
                    <?php foreach ($global_columns as $col):
                        $cid = (int)$col['id'];
                        $lid = (int)$list_row['id'];
                        $val = $per_list_cells[$lid][$cid] ?? null;
                    ?>
                    <td class="text-end text-nowrap">
                        <?php if ($val === null): ?>
                        <span class="text-muted">—</span>
                        <?php elseif ($col['data_type'] === 'boolean'): ?>
                        <?= in_array($val, ['1', 'true'], true) ? '✓' : '✗' ?>
                        <?php else: ?>
                        <?php $n = (float)$val; echo ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.'); ?>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach;
        },
        function() use ($global_columns, $per_list_totals, $col_list_counts) {
            ?>
            <tr class="fw-bold">
                <td colspan="2">Gesamt</td>
                <?php foreach ($global_columns as $col):
                    $cid    = (int)$col['id'];
                    $totals = $per_list_totals[$cid] ?? null;
                ?>
                <td class="text-end text-nowrap">
                    <?php if ($col['data_type'] === 'number'): ?>
                    <?php $n = (float)($totals['sum'] ?? 0); echo ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.'); ?>
                    <?php else: ?>
                    <?php
                        $count_true  = (int)($totals['count_true'] ?? 0);
                        $total_lists = (int)($col_list_counts[$cid] ?? 0);
                        $pct = $total_lists > 0 ? round($count_true / $total_lists * 100) : 0;
                        echo $count_true . ' <small class="text-muted fw-normal">(' . $pct . '%)</small>';
                    ?>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php
        }
    ); ?>
    <?php endif; ?>

<?php endif; ?>
