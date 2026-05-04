<?php
// src/templates/coach/list_detail.php — List table view for coach
// Per D-03: HTML table; global columns first, then local.
// Per D-04: Bootstrap table-responsive for horizontal scroll.
// Per D-05: empty cells show blank (not placeholder text).
// Per D-07: replaced per-row "Bearbeiten" navigate-away with inline-edit form (260430-rbt).
// Variables: $list, $columns, $players, $cells (map [player_id][column_id] => value)
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
        <a href="/coach/lists/<?= (int)$list['id'] ?>/settings"
           class="btn btn-sm btn-outline-secondary min-touch">
            <i class="bi bi-gear me-1"></i>Einstellungen
        </a>
    </div>
</div>

<?php if (!empty($list['description'])): ?>
<p class="text-muted small mb-3"><?= e($list['description']) ?></p>
<?php endif; ?>

<!-- Add local column form — inline at top (D-10) -->
<details class="mb-3">
    <summary class="text-muted small" style="cursor:pointer; list-style:none;">
        <i class="bi bi-plus-circle me-1"></i>Lokale Spalte hinzufügen
    </summary>
    <div class="card card-body mt-2" style="max-width: 400px;">
        <form method="POST" action="/coach/lists/<?= (int)$list['id'] ?>/columns/create">
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
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="coach_only" value="1" id="coach_only_chk">
                        <label class="form-check-label small" for="coach_only_chk">
                            Nur für Trainer
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </div>
</details>

<?php if (empty($players)): ?>
<div class="text-center py-5">
    <p class="text-muted">Keine aktiven Spieler im Team.</p>
</div>
<?php elseif (empty($columns)): ?>
<div class="alert alert-info">
    Noch keine Spalten definiert.
    <a href="/coach/columns">Globale Spalten anlegen</a> oder eine lokale Spalte oben hinzufügen.
</div>
<?php else: ?>

<form method="POST" action="/coach/lists/<?= (int)$list['id'] ?>">
    <?= csrf_field() ?>

    <!-- Per D-04: Bootstrap table-responsive for horizontal scroll on mobile -->
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Spieler</th>
                    <?php foreach ($columns as $col): ?>
                    <th class="text-nowrap">
                        <?= e($col['name']) ?>
                        <?php if ($col['list_id'] === null): ?>
                            <span class="badge bg-light text-dark border ms-1" title="Globale Spalte">G</span>
                        <?php endif; ?>
                        <?php if ($col['list_id'] !== null && !empty($col['coach_only'])): ?>
                            <span class="badge bg-danger ms-1" title="Nur für Trainer">T</span>
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
                                echo '<div class="form-check form-switch mb-0">'
                                    . '<input class="form-check-input" type="checkbox" role="switch"'
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

<?php endif; ?>

<div class="mt-3">
    <a href="/coach/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Listen
    </a>
</div>
