<?php // src/templates/coordinator/stats.php — Coach statistics page (STAT-01, STAT-02, STAT-03) ?>

<!-- Filter form: list dropdown + date range, GET method (PRG pattern) -->
<form method="get" action="/coordinator/stats" class="row g-2 mb-4 align-items-end">
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
        <div class="form-check form-switch d-flex align-items-center gap-2 mb-0">
            <input class="form-check-input" type="checkbox" role="switch"
                   style="width:3em;height:1.75em;cursor:pointer;"
                   name="include_undated" id="include_undated" value="1"
                   <?= $filter_include_undated ? 'checked' : '' ?>>
            <label class="form-check-label mb-0" for="include_undated">Ohne Datum einschließen</label>
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filtern</button>
        <a href="/coordinator/stats" class="btn btn-sm btn-outline-secondary ms-1">Zurücksetzen</a>
    </div>
</form>

<?php if (empty($global_columns)): ?>
    <div class="alert alert-info">
        Noch keine globalen Spalten definiert. Legen Sie globale Spalten unter
        <a href="/coordinator/columns">Spalten</a> an.
    </div>
<?php elseif (empty($player_order)): ?>
    <div class="alert alert-info">Keine aktiven Mitglieder im Team.</div>
<?php else: ?>
    <h5 class="mb-3">Mitgliederstatistiken</h5>
    <div class="table-responsive mb-5">
        <table class="table table-sm table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Mitglied</th>
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
                            <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
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
    <!-- Spalten-Dropdown: filtert nur die Rangliste auf eine globale Spalte -->
    <form method="get" action="/coordinator/stats" class="mb-3 d-flex align-items-center gap-2 flex-wrap">
        <?php if ($filter_list_id): ?><input type="hidden" name="list_id" value="<?= (int)$filter_list_id ?>"><?php endif; ?>
        <?php if ($filter_date_from): ?><input type="hidden" name="date_from" value="<?= e($filter_date_from) ?>"><?php endif; ?>
        <?php if ($filter_date_to): ?><input type="hidden" name="date_to" value="<?= e($filter_date_to) ?>"><?php endif; ?>
        <?php if ($filter_include_undated): ?><input type="hidden" name="include_undated" value="1"><?php endif; ?>
        <input type="hidden" name="sort_col" value="<?= (int)$sort_col_id ?>">
        <input type="hidden" name="sort_win" value="<?= e($sort_win) ?>">
        <label for="col_filter_select" class="form-label mb-0 small fw-medium">Spalte:</label>
        <select name="col_filter" id="col_filter_select" class="form-select form-select-sm" style="max-width:200px;" onchange="this.form.submit()">
            <?php foreach ($global_columns as $col): ?>
                <option value="<?= (int)$col['id'] ?>" <?= $col_filter === (int)$col['id'] ? 'selected' : '' ?>>
                    <?= e($col['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <h5 class="mb-3">Rangliste</h5>
    <p class="text-muted small mb-3">
        Klicke auf eine Spaltenüberschrift, um die Rangliste danach zu sortieren.
        <em>Gesamt</em> berücksichtigt die Datumsfilter oben; die Zeitfenster verwenden feste Intervalle.
    </p>

    <?php
    // Helper: build URL for sorting by given col_id + win, preserving current filter state
    function ranking_sort_url(int $col_id, string $win, array $current_get): string {
        $params = array_filter([
            'list_id'         => $current_get['list_id']    ?? '',
            'date_from'       => $current_get['date_from']  ?? '',
            'date_to'         => $current_get['date_to']    ?? '',
            'include_undated' => ($current_get['include_undated'] ?? '') ? '1' : '',
            'col_filter'      => $current_get['col_filter'] ?? '',
            'sort_col'        => $col_id,
            'sort_win'        => $win,
        ], fn($v) => $v !== '');
        return '/coordinator/stats?' . http_build_query($params);
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
                    <th rowspan="2" class="align-middle text-nowrap">Mitglied</th>
                    <?php foreach ($global_columns as $col): ?>
                        <?php if ($col_filter !== 0 && (int)$col['id'] !== $col_filter) continue; ?>
                        <th colspan="4" class="text-center border-start"><?= e($col['name']) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($global_columns as $col): ?>
                        <?php if ($col_filter !== 0 && (int)$col['id'] !== $col_filter) continue; ?>
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
                        <td class="fw-semibold text-nowrap"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <?php foreach ($global_columns as $col): ?>
                            <?php if ($col_filter !== 0 && (int)$col['id'] !== $col_filter) continue; ?>
                            <?php $cid = (int)$col['id']; ?>
                            <?php foreach (array_keys($windows) as $win_key): ?>
                                <?php
                                    $val         = $p['cols'][$cid][$win_key] ?? 0;
                                    $active_cell = ($sort_col_id === $cid && $sort_win === $win_key);
                                    $n           = (float)$val;
                                    $display     = ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.');
                                    // Percentage: boolean = val/cnt; number = val/col_total
                                    $cnt_key = 'cnt_' . $win_key;
                                    $pct = null;
                                    if ($col['data_type'] === 'boolean') {
                                        $cnt = $p['cols'][$cid][$cnt_key] ?? 0;
                                        if ($cnt > 0) { $pct = round($val / $cnt * 100); }
                                    } else {
                                        $total = $col_totals[$cid][$win_key] ?? 0;
                                        if ($total > 0) { $pct = round($val / $total * 100); }
                                    }
                                ?>
                                <td class="text-end text-nowrap border-start<?= $active_cell ? ' table-active fw-semibold' : '' ?>">
                                    <?= $display ?>
                                    <?php if ($pct !== null): ?>
                                        <small class="text-muted fw-normal ms-1">(<?= $pct ?>%)</small>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Listenübersicht pro Mitglied ───────────────────────────────────── -->
    <hr class="my-4">
    <h5 class="mb-3">Listenübersicht</h5>

    <form method="get" action="/coordinator/stats" class="mb-3 d-flex align-items-center gap-2 flex-wrap">
        <?php if ($filter_list_id): ?><input type="hidden" name="list_id" value="<?= (int)$filter_list_id ?>"><?php endif; ?>
        <?php if ($filter_date_from): ?><input type="hidden" name="date_from" value="<?= e($filter_date_from) ?>"><?php endif; ?>
        <?php if ($filter_date_to): ?><input type="hidden" name="date_to" value="<?= e($filter_date_to) ?>"><?php endif; ?>
        <?php if ($filter_include_undated): ?><input type="hidden" name="include_undated" value="1"><?php endif; ?>
        <label for="member_selector" class="form-label mb-0 small fw-medium">Mitglied:</label>
        <select name="member_id" id="member_selector" class="form-select form-select-sm" style="max-width:220px;">
            <option value="">Alle Mitglieder</option>
            <?php foreach ($all_members as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= $selected_member_id === (int)$m['id'] ? 'selected' : '' ?>>
                    <?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">Anzeigen</button>
        <?php if ($selected_member_id): ?>
            <?php
                $reset_params = array_filter([
                    'list_id'         => $filter_list_id ? (string)$filter_list_id : '',
                    'date_from'       => $filter_date_from ?? '',
                    'date_to'         => $filter_date_to ?? '',
                    'include_undated' => $filter_include_undated ? '1' : '',
                ], fn($v) => $v !== '');
                $reset_url = '/coordinator/stats' . (!empty($reset_params) ? '?' . http_build_query($reset_params) : '');
            ?>
            <a href="<?= e($reset_url) ?>" class="btn btn-sm btn-outline-secondary">Zurücksetzen</a>
        <?php endif; ?>
    </form>

    <?php if ($selected_member_id && !empty($mod_per_list_rows)): ?>
        <h6 class="mb-3">Listenübersicht: <?= e($selected_member_name) ?></h6>
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
                    <?php foreach ($mod_per_list_rows as $list_row): ?>
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
                                        $val = $mod_per_list_cells[$lid][$cid] ?? null;
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
                                    $totals = $mod_per_list_totals[$cid] ?? null;
                                    if ($col['data_type'] === 'number') {
                                        $n = (float)($totals['sum'] ?? 0);
                                        echo ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.');
                                    } else {
                                        $count_true  = (int)($totals['count_true'] ?? 0);
                                        $total_lists = (int)($mod_col_list_counts[$cid] ?? 0);
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
    <?php elseif ($selected_member_id): ?>
        <p class="text-muted small">Keine Listen mit globalen Spalten für dieses Mitglied gefunden.</p>
    <?php elseif (!empty($all_lists_rows)): ?>
        <h6 class="mb-3">Listenübersicht: Alle Mitglieder</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">Liste</th>
                        <th class="text-nowrap text-muted small fw-normal">Datum</th>
                        <?php foreach ($global_columns as $col): ?>
                            <th class="text-end text-nowrap">
                                <?= e($col['name']) ?>
                                <small class="text-muted fw-normal d-block">
                                    <?= $col['data_type'] === 'number' ? 'Summe' : 'Anzahl' ?>
                                </small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_lists_rows as $lid => $list_row): ?>
                        <tr>
                            <td class="fw-semibold text-nowrap"><?= e($list_row['name']) ?></td>
                            <td class="text-nowrap text-muted small">
                                <?= $list_row['date'] ? date('d.m.Y', strtotime($list_row['date'])) : '—' ?>
                            </td>
                            <?php foreach ($global_columns as $col): ?>
                                <td class="text-end text-nowrap">
                                    <?php
                                        $cid   = (int)$col['id'];
                                        $entry = $all_lists_agg[$lid][$cid] ?? null;
                                        if ($entry === null) {
                                            echo '<span class="text-muted">—</span>';
                                        } elseif ($col['data_type'] === 'boolean') {
                                            $cnt = (int)$entry['val'];
                                            $pct = $total_active_members > 0 ? round($cnt / $total_active_members * 100) : 0;
                                            echo $cnt . ' / ' . $total_active_members . ' <small class="text-muted fw-normal">(' . $pct . '%)</small>';
                                        } else {
                                            $n = (float)$entry['val'];
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
                                    $cid            = (int)$col['id'];
                                    $total_val      = 0.0;
                                    $list_count_col = 0;
                                    foreach ($all_lists_rows as $lid => $unused) {
                                        $entry = $all_lists_agg[$lid][$cid] ?? null;
                                        if ($entry !== null) {
                                            $total_val += (float)$entry['val'];
                                            $list_count_col++;
                                        }
                                    }
                                    if ($col['data_type'] === 'boolean') {
                                        $possible = $total_active_members * $list_count_col;
                                        $pct      = $possible > 0 ? round($total_val / $possible * 100) : 0;
                                        echo (int)$total_val . ' / ' . $possible . ' <small class="text-muted fw-normal">(' . $pct . '%)</small>';
                                    } else {
                                        $n = $total_val;
                                        echo ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.');
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

<script>
(function() {
    var KEY = 'stats_scroll';
    // Restore after full render so page height is established
    window.addEventListener('load', function() {
        var saved = sessionStorage.getItem(KEY);
        if (saved !== null) { window.scrollTo(0, +saved); sessionStorage.removeItem(KEY); }
    });
    document.addEventListener('DOMContentLoaded', function() {
        function save() { sessionStorage.setItem(KEY, window.scrollY); }
        document.querySelectorAll('form[method="get"]').forEach(function(f) {
            f.addEventListener('submit', save);
            // form.submit() skips the submit event — catch change on selects too
            f.querySelectorAll('select').forEach(function(s) {
                s.addEventListener('change', save);
            });
        });
    });
})();
</script>
