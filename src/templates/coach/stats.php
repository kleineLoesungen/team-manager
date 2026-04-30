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
        <label for="date_from" class="form-label form-label-sm mb-1">Von</label>
        <input type="date" name="date_from" id="date_from" class="form-control form-control-sm"
               value="<?= e($filter_date_from ?? '') ?>">
    </div>
    <div class="col-auto">
        <label for="date_to" class="form-label form-label-sm mb-1">Bis</label>
        <input type="date" name="date_to" id="date_to" class="form-control form-control-sm"
               value="<?= e($filter_date_to ?? '') ?>">
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
<?php endif; ?>

<?php if (!empty($global_columns) && !empty($player_order)): ?>
    <h5 class="mb-3">Rangliste</h5>

    <!-- Leaderboard column selector -->
    <form method="get" action="/coach/stats" class="d-flex align-items-center gap-2 mb-3">
        <?php if ($filter_list_id):  ?><input type="hidden" name="list_id"   value="<?= (int)$filter_list_id ?>">  <?php endif; ?>
        <?php if ($filter_date_from): ?><input type="hidden" name="date_from" value="<?= e($filter_date_from) ?>"> <?php endif; ?>
        <?php if ($filter_date_to):   ?><input type="hidden" name="date_to"   value="<?= e($filter_date_to) ?>">   <?php endif; ?>
        <label for="sort_by" class="form-label mb-0 text-nowrap">Sortieren nach:</label>
        <select name="sort_by" id="sort_by" class="form-select form-select-sm" style="max-width: 200px;"
                onchange="this.form.submit()">
            <?php foreach ($global_columns as $col): ?>
                <option value="<?= (int)$col['id'] ?>"
                    <?= $sort_by_id === (int)$col['id'] ? 'selected' : '' ?>>
                    <?= e($col['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-secondary">OK</button></noscript>
    </form>

    <?php if (!empty($leaderboard)): ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Spieler</th>
                        <th class="text-end text-nowrap">
                            <?= $leaderboard_column ? e($leaderboard_column['name']) : '' ?>
                            <small class="text-muted fw-normal d-block">
                                <?= ($leaderboard_column && $leaderboard_column['data_type'] === 'number') ? 'Summe' : 'Anzahl' ?>
                            </small>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; foreach ($leaderboard as $row): ?>
                        <tr>
                            <td class="text-muted"><?= $rank++ ?></td>
                            <td class="fw-semibold text-nowrap">
                                <?= e($row['last_name'] . ', ' . $row['first_name']) ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php
                                    $v = $row['rank_value'];
                                    if ($leaderboard_column && $leaderboard_column['data_type'] === 'boolean') {
                                        echo (int)$v;
                                    } else {
                                        $n = (float)$v;
                                        echo ($n == floor($n)) ? (int)$n : number_format($n, 2, ',', '.');
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
