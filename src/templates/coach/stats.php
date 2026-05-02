<?php // src/templates/coach/stats.php — Coach statistics page (STAT-01, STAT-02, STAT-03) ?>

<!-- Filter form: list dropdown + date range, GET method (PRG pattern) -->
<form method="get" action="/coach/stats" class="row g-2 mb-4 align-items-end">
    <div class="col-auto">
        <label for="list_filter" class="form-label form-label-sm mb-1">Liste</label>
        <select name="list_id" id="list_filter" class="form-select form-select-sm" style="max-width: 200px;">
            <option value="">Alle Listen</option>
            <?php foreach ($available_lists as $list): ?>
                <option value="<?= (int)$list['id'] ?>"
                    <?= $filter_list_id === (int)$list['id'] ? 'selected' : '' ?>>
                    <?= e($list['name']) ?>
                    <?php if ($list['visibility'] === 'private'): ?>
                        (privat)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label for="date_from" class="form-label form-label-sm mb-1">Listendatum von</label>
        <input type="date" name="date_from" id="date_from" class="form-control form-control-sm"
               value="<?= e($filter_date_from ?? '') ?>">
    </div>
    <div class="col-auto">
        <label for="date_to" class="form-label form-label-sm mb-1">bis</label>
        <input type="date" name="date_to" id="date_to" class="form-control form-control-sm"
               value="<?= e($filter_date_to ?? '') ?>">
    </div>
    <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" name="include_undated" id="include_undated"
                   value="1" <?= $filter_include_undated ? 'checked' : '' ?>>
            <label class="form-check-label small" for="include_undated">
                Ohne Datum einschließen
            </label>
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filtern</button>
        <a href="/coach/stats" class="btn btn-sm btn-outline-secondary ms-1">Zurücksetzen</a>
    </div>
</form>

<?php if (empty($global_columns)): ?>
    <div class="alert alert-info">
        Noch keine globalen Spalten definiert. Legen Sie globale Spalten unter
        <a href="/coach/columns">Spalten</a> an.
    </div>
<?php elseif (empty($player_order)): ?>
    <div class="alert alert-info">Keine aktiven Spieler im Team.</div>
<?php else: ?>
    <h5 class="mb-3">Spielerstatistiken</h5>
    <div class="table-responsive mb-5">
        <table class="table table-sm table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Spieler</th>
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
                <?php foreach ($player_order as $pid): ?>
                    <?php $p = $player_stats[$pid]; ?>
                    <tr>
                        <td class="fw-semibold text-nowrap">
                            <?= e($p['last_name'] . ', ' . $p['first_name']) ?>
                        </td>
                        <?php foreach ($global_columns as $col): ?>
                            <td class="text-end text-nowrap">
                                <?php
                                    $val = $p['cols'][(int)$col['id']] ?? null;
                                    if ($col['data_type'] === 'boolean') {
                                        echo ($val !== null) ? (int)$val : '0';
                                    } else {
                                        // number: display as integer if whole number, else 2 decimals
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
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Rangliste mit Zeitfenstern (STAT-03) ───────────────────────── -->
    <h5 class="mb-3">Rangliste</h5>
    <p class="text-muted small mb-3">
        Klicken Sie auf eine Spaltenüberschrift, um die Rangliste danach zu sortieren.
        <em>Gesamt</em> berücksichtigt die Datumsfilter oben; die Zeitfenster verwenden feste Intervalle.
    </p>

    <?php
    // Helper: build URL for sorting by given col_id + win, preserving current filter state
    function ranking_sort_url(int $col_id, string $win, array $current_get): string {
        $params = array_filter([
            'list_id'         => $current_get['list_id']   ?? '',
            'date_from'       => $current_get['date_from'] ?? '',
            'date_to'         => $current_get['date_to']   ?? '',
            'include_undated' => ($current_get['include_undated'] ?? '') ? '1' : '',
            'sort_col'        => $col_id,
            'sort_win'        => $win,
        ], fn($v) => $v !== '');
        return '/coach/stats?' . http_build_query($params);
    }

    $windows = [
        'all'    => 'Gesamt',
        '4w'     => 'Letzte&nbsp;4&nbsp;Wo.',
        '4_8w'   => '4–8&nbsp;Wo.',
        '8_12w'  => '8–12&nbsp;Wo.',
    ];
    ?>

    <div class="table-responsive">
        <table class="table table-sm table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th rowspan="2" class="align-middle text-nowrap">Spieler</th>
                    <?php foreach ($global_columns as $col): ?>
                        <th colspan="4" class="text-center border-start"><?= e($col['name']) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($global_columns as $col): ?>
                        <?php foreach ($windows as $win_key => $win_label): ?>
                            <?php $active = ($sort_col_id === (int)$col['id'] && $sort_win === $win_key); ?>
                            <th class="text-end text-nowrap border-start<?= $active ? ' text-primary' : '' ?>">
                                <a href="<?= ranking_sort_url((int)$col['id'], $win_key, $_GET) ?>"
                                   class="text-decoration-none<?= $active ? ' text-primary fw-bold' : ' text-body' ?>">
                                    <?= $win_label ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking_order as $pid): ?>
                    <?php $p = $ranking[$pid]; ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= e($p['last_name'] . ', ' . $p['first_name']) ?></td>
                        <?php foreach ($global_columns as $col): ?>
                            <?php $cid = (int)$col['id']; ?>
                            <?php foreach (array_keys($windows) as $win_key): ?>
                                <?php
                                    $val         = $p['cols'][$cid][$win_key] ?? 0;
                                    $active_cell = ($sort_col_id === $cid && $sort_win === $win_key);
                                    $n           = (float)$val;
                                    $display     = ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.');
                                ?>
                                <td class="text-end text-nowrap border-start<?= $active_cell ? ' table-active fw-semibold' : '' ?>">
                                    <?= $display ?>
                                </td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
