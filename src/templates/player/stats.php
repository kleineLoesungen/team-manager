<?php // src/templates/player/stats.php — Player statistics: own row only (STAT-01, D-04, D-05) ?>

<?php if (empty($global_columns)): ?>
    <div class="alert alert-info">
        Ihr Trainer hat noch keine globalen Spalten definiert. Sobald globale Spalten angelegt sind, erscheinen hier Ihre Statistiken.
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

<?php endif; ?>
