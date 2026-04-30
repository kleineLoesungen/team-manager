<?php // src/templates/player/stats.php — Player statistics: own row only (STAT-01, D-04, D-05) ?>

<?php if (empty($global_columns)): ?>
    <div class="alert alert-info">
        Ihr Trainer hat noch keine globalen Spalten definiert. Sobald globale Spalten angelegt sind, erscheinen hier Ihre Statistiken.
    </div>
<?php else: ?>

    <p class="text-muted mb-4 small">
        Statistiken werden aus allen öffentlichen und geschützten Listen berechnet. Private Listen werden nicht einbezogen.
    </p>

    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <?php foreach ($global_columns as $col): ?>
                        <th class="text-nowrap text-end">
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
                    <?php foreach ($global_columns as $col): ?>
                        <td class="text-end text-nowrap fw-semibold">
                            <?php
                                $val = $player_stats[(int)$col['id']] ?? null;
                                if ($col['data_type'] === 'boolean') {
                                    echo ($val !== null) ? (int)$val : '0';
                                } else {
                                    if ($val === null) {
                                        echo '0';
                                    } else {
                                        $n = (float)$val;
                                        echo ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.');
                                    }
                                }
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

<?php endif; ?>
