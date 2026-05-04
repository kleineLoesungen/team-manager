<?php
// src/templates/coach/list_detail.php — List table view for coach
// Per D-03: HTML table; global columns first, then local.
// Per D-04: Bootstrap table-responsive for horizontal scroll.
// Per D-05: empty cells show blank (not placeholder text).
// Per D-07: replaced per-row "Bearbeiten" navigate-away with inline-edit form (260430-rbt).
// Variables: $list, $columns, $players, $cells (map [row_id][column_id] => value)
//            $is_free_list (bool), $free_rows (array), $confirm_delete (array|null)
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <?php
            $badge_class = match($list['visibility']) {
                'public'    => 'bg-success',
                'protected' => 'bg-warning text-dark',
                'private'   => 'bg-secondary',
                default     => 'bg-secondary',
            };
            $badge_label = match($list['visibility']) {
                'public'    => 'Öffentlich',
                'protected' => 'Geschützt',
                'private'   => 'Privat',
                default     => e($list['visibility']),
            };
        ?>
        <span class="badge <?= $badge_class ?> me-2"><?= $badge_label ?></span>
        <?php if (!empty($list['date'])): ?>
        <span class="text-muted small"><?= e((new DateTime($list['date']))->format('d.m.Y')) ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="/moderator/lists/<?= (int)$list['id'] ?>/settings"
           class="btn btn-sm btn-outline-secondary min-touch">
            <i class="bi bi-gear me-1"></i>Einstellungen
        </a>
    </div>
</div>

<?php if (!empty($list['description'])): ?>
<p class="text-muted small mb-3"><?= e($list['description']) ?></p>
<?php endif; ?>

<?php if ($is_free_list): ?>

<!-- FREE LIST: add row form -->
<details class="mb-3">
    <summary class="text-muted small" style="cursor:pointer; list-style:none;">
        <i class="bi bi-plus-circle me-1"></i>Zeile hinzufügen
    </summary>
    <div class="card card-body mt-2" style="max-width: 400px;">
        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_row">
            <div class="row g-2">
                <div class="col">
                    <input type="text" name="row_label" class="form-control form-control-sm"
                           placeholder="Zeilenbezeichnung" required maxlength="200">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary min-touch">Hinzufügen</button>
                </div>
            </div>
        </form>
    </div>
</details>

<!-- FREE LIST: add local column form -->
<details class="mb-3">
    <summary class="text-muted small" style="cursor:pointer; list-style:none;">
        <i class="bi bi-plus-circle me-1"></i>Lokale Spalte hinzufügen
    </summary>
    <div class="card card-body mt-2" style="max-width: 400px;">
        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/columns/create">
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col">
                    <input type="text" name="name" class="form-control form-control-sm"
                           placeholder="Spaltenname" maxlength="100" required>
                </div>
                <div class="col-auto">
                    <select name="data_type" class="form-select form-select-sm">
                        <option value="boolean">Ja/Nein</option>
                        <option value="number">Zahl</option>
                        <option value="text">Text</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary min-touch">Hinzufügen</button>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-12">
                    <div class="form-check form-switch" style="min-height:1.75em;">
                        <input class="form-check-input" type="checkbox" role="switch"
                               style="width:3em;height:1.75em;cursor:pointer;"
                               name="coach_only" value="1" id="coach_only_free_chk">
                        <label class="form-check-label small" for="coach_only_free_chk">
                            Nur für Moderatoren
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </div>
</details>

<?php if ($confirm_delete !== null): ?>
<!-- Two-step delete confirmation for free row -->
<div class="alert alert-danger">
    <strong>Zeile "<?= e($confirm_delete['label']) ?>" wirklich löschen?</strong>
    <br><span class="small text-muted">Alle Zellwerte dieser Zeile werden ebenfalls gelöscht. Diese Aktion kann nicht rückgängig gemacht werden.</span>
    <div class="mt-2 d-flex gap-2">
        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_row">
            <input type="hidden" name="row_id" value="<?= (int)$confirm_delete['id'] ?>">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn btn-sm btn-danger">Ja, löschen</button>
        </form>
        <a href="/moderator/lists/<?= (int)$list['id'] ?>" class="btn btn-sm btn-outline-secondary">Abbrechen</a>
    </div>
</div>
<?php endif; ?>

<?php if (empty($free_rows) && empty($columns)): ?>
<div class="text-center py-5">
    <p class="text-muted">Noch keine Zeilen und Spalten definiert.</p>
</div>
<?php elseif (empty($free_rows)): ?>
<div class="alert alert-info">Noch keine Zeilen definiert. Füge oben eine Zeile hinzu.</div>
<?php elseif (empty($columns)): ?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Zeile</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($free_rows as $row): ?>
            <tr>
                <td class="fw-medium"><?= e($row['label']) ?></td>
                <td>
                    <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_row">
                        <input type="hidden" name="row_id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Löschen</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="alert alert-info mt-2">Noch keine Spalten definiert. Füge oben eine lokale Spalte hinzu.</div>
<?php else: ?>

<?php foreach ($free_rows as $row): ?>
<form id="delete-row-<?= (int)$row['id'] ?>" method="POST"
      action="/moderator/lists/<?= (int)$list['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_row">
    <input type="hidden" name="row_id" value="<?= (int)$row['id'] ?>">
</form>
<?php endforeach; ?>

<form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_cells">

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Zeile</th>
                    <?php foreach ($columns as $col): ?>
                    <th class="text-nowrap">
                        <?= e($col['name']) ?>
                        <?php if (!empty($col['coach_only'])): ?>
                            <span class="badge bg-danger ms-1" title="Nur für Moderatoren">T</span>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                    <th class="text-nowrap">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($free_rows as $row): ?>
                <tr>
                    <td class="text-nowrap fw-medium"><?= e($row['label']) ?></td>
                    <?php foreach ($columns as $col): ?>
                    <td>
                        <?php
                            $val = $cells[(int)$row['id']][(int)$col['id']] ?? null;
                            if ($col['data_type'] === 'boolean') {
                                $checked = ($val === '1') ? 'checked' : '';
                                echo '<div class="form-check form-switch mb-0" style="min-height:1.75em;">'
                                    . '<input class="form-check-input" type="checkbox" role="switch"'
                                    . ' style="width:3em;height:1.75em;cursor:pointer;"'
                                    . ' name="cells[' . (int)$row['id'] . '][' . (int)$col['id'] . ']"'
                                    . ' value="1" ' . $checked . '>'
                                    . '</div>';
                            } elseif ($col['data_type'] === 'number') {
                                $escaped = ($val !== null && $val !== '') ? e($val) : '';
                                echo '<input type="number" class="form-control form-control-sm"
                                      style="min-width:70px; max-width:100px"
                                      name="cells[' . (int)$row['id'] . '][' . (int)$col['id'] . ']"
                                      value="' . $escaped . '">';
                            } else {
                                $escaped = ($val !== null) ? e($val) : '';
                                echo '<input type="text" class="form-control form-control-sm"
                                      style="min-width:100px"
                                      name="cells[' . (int)$row['id'] . '][' . (int)$col['id'] . ']"
                                      value="' . $escaped . '" maxlength="255">';
                            }
                        ?>
                    </td>
                    <?php endforeach; ?>
                    <td>
                        <button type="submit" form="delete-row-<?= (int)$row['id'] ?>"
                                class="btn btn-sm btn-outline-danger">Löschen</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php
                // Totals row for free lists
                $col_totals = [];
                foreach ($columns as $col) {
                    $cid = (int)$col['id'];
                    if ($col['data_type'] === 'number') {
                        $sum = 0;
                        foreach ($free_rows as $row) {
                            $v = $cells[(int)$row['id']][$cid] ?? null;
                            if ($v !== null && $v !== '' && is_numeric($v)) {
                                $sum += (float)$v;
                            }
                        }
                        $col_totals[$cid] = ($sum == floor($sum))
                            ? (int)$sum
                            : number_format($sum, 2, ',', '.');
                    } elseif ($col['data_type'] === 'boolean') {
                        $count = 0;
                        foreach ($free_rows as $row) {
                            if (($cells[(int)$row['id']][$cid] ?? null) === '1') {
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
                    <td class="text-nowrap fw-bold">Gesamt</td>
                    <?php foreach ($columns as $col): ?>
                    <td class="text-nowrap fw-bold"><?= $col_totals[(int)$col['id']] ?></td>
                    <?php endforeach; ?>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary min-touch">Speichern</button>
    </div>
</form>

<?php endif; // free list: rows/columns states ?>

<?php else: ?>
<!-- MEMBER LIST: existing layout -->

<!-- Add local column form — inline at top (D-10) -->
<details class="mb-3">
    <summary class="text-muted small" style="cursor:pointer; list-style:none;">
        <i class="bi bi-plus-circle me-1"></i>Lokale Spalte hinzufügen
    </summary>
    <div class="card card-body mt-2" style="max-width: 400px;">
        <form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>/columns/create">
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col">
                    <input type="text" name="name" class="form-control form-control-sm"
                           placeholder="Spaltenname" maxlength="100" required>
                </div>
                <div class="col-auto">
                    <select name="data_type" class="form-select form-select-sm">
                        <option value="boolean">Ja/Nein</option>
                        <option value="number">Zahl</option>
                        <option value="text">Text</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary min-touch">Hinzufügen</button>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-12">
                    <div class="form-check form-switch" style="min-height:1.75em;">
                        <input class="form-check-input" type="checkbox" role="switch"
                               style="width:3em;height:1.75em;cursor:pointer;"
                               name="coach_only" value="1" id="coach_only_member_chk">
                        <label class="form-check-label small" for="coach_only_member_chk">
                            Nur für Moderatoren
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </div>
</details>

<?php if (empty($players)): ?>
<div class="text-center py-5">
    <p class="text-muted">Keine aktiven Mitglieder im Team.</p>
</div>
<?php elseif (empty($columns)): ?>
<div class="alert alert-info">
    Noch keine Spalten definiert.
    <a href="/moderator/columns">Globale Spalten anlegen</a> oder eine lokale Spalte oben hinzufügen.
</div>
<?php else: ?>

<form method="POST" action="/moderator/lists/<?= (int)$list['id'] ?>">
    <?= csrf_field() ?>

    <!-- Per D-04: Bootstrap table-responsive for horizontal scroll on mobile -->
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Mitglied</th>
                    <?php foreach ($columns as $col): ?>
                    <th class="text-nowrap">
                        <?= e($col['name']) ?>
                        <?php if ($col['list_id'] === null): ?>
                            <span class="badge bg-light text-dark border ms-1" title="Globale Spalte">G</span>
                        <?php endif; ?>
                        <?php if ($col['list_id'] !== null && !empty($col['coach_only'])): ?>
                            <span class="badge bg-danger ms-1" title="Nur für Moderatoren">T</span>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($players as $player): ?>
                <tr>
                    <td class="text-nowrap fw-medium">
                        <?= e($player['first_name'] . ' ' . $player['last_name']) ?>
                    </td>
                    <?php foreach ($columns as $col): ?>
                    <td>
                        <?php
                            $val = $cells[(int)$player['id']][(int)$col['id']] ?? null;
                            if ($col['data_type'] === 'boolean') {
                                $checked = ($val === '1') ? 'checked' : '';
                                echo '<div class="form-check form-switch mb-0" style="min-height:1.75em;">'
                                    . '<input class="form-check-input" type="checkbox" role="switch"'
                                    . ' style="width:3em;height:1.75em;cursor:pointer;"'
                                    . ' name="cells[' . (int)$player['id'] . '][' . (int)$col['id'] . ']"'
                                    . ' value="1" ' . $checked . '>'
                                    . '</div>';
                            } elseif ($col['data_type'] === 'number') {
                                $escaped = ($val !== null && $val !== '') ? e($val) : '';
                                echo '<input type="number" class="form-control form-control-sm"
                                      style="min-width:70px; max-width:100px"
                                      name="cells[' . (int)$player['id'] . '][' . (int)$col['id'] . ']"
                                      value="' . $escaped . '">';
                            } else {
                                $escaped = ($val !== null) ? e($val) : '';
                                echo '<input type="text" class="form-control form-control-sm"
                                      style="min-width:100px"
                                      name="cells[' . (int)$player['id'] . '][' . (int)$col['id'] . ']"
                                      value="' . $escaped . '" maxlength="255">';
                            }
                        ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php
                // Build totals per column
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
                    <td class="text-nowrap fw-bold">Gesamt</td>
                    <?php foreach ($columns as $col): ?>
                    <td class="text-nowrap fw-bold"><?= $col_totals[(int)$col['id']] ?></td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary min-touch">Speichern</button>
    </div>
</form>

<?php endif; // member list: players/columns states ?>

<?php endif; // is_free_list ?>

<div class="mt-3">
    <a href="/moderator/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Listen
    </a>
</div>
