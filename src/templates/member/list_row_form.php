<?php
// src/templates/player/list_row_form.php — Player row edit form (CELL-01)
// Variables: $list (id, name), $player (id, first_name, last_name),
//            $columns, $existing_cells ([column_id => value])
?>

<div class="mb-3">
    <a href="/member/lists/<?= (int)$list['id'] ?>" class="text-muted small">
        <i class="bi bi-arrow-left me-1"></i><?= e($list['name']) ?>
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <span class="fw-semibold">
            <?= e($player['first_name'] . ' ' . $player['last_name']) ?>
        </span>
        <span class="badge bg-primary ms-2">Meine Zeile</span>
    </div>
    <div class="card-body">
        <form method="POST"
              action="/member/lists/<?= (int)$list['id'] ?>/rows/<?= (int)$player['id'] ?>/edit">
            <?= csrf_field() ?>

            <?php if (empty($columns)): ?>
            <p class="text-muted">Keine Spalten in dieser Liste.</p>
            <?php else: ?>

            <?php foreach ($columns as $col): ?>
            <div class="mb-3">
                <label class="form-label fw-medium"><?= e($col['name']) ?></label>

                <?php
                    $col_id      = (int)$col['id'];
                    $current_val = $existing_cells[$col_id] ?? null;
                ?>

                <?php if ($col['data_type'] === 'boolean'): ?>
                    <div class="form-check form-switch" style="min-height:2em;">
                        <input class="form-check-input" type="checkbox" role="switch"
                               style="width:3.5em;height:2em;cursor:pointer;"
                               name="cells[<?= $col_id ?>]" value="1"
                               id="cell_<?= $col_id ?>"
                               <?= $current_val === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label ms-1 align-self-center" for="cell_<?= $col_id ?>">Ja</label>
                    </div>

                <?php elseif ($col['data_type'] === 'number'): ?>
                    <input type="number" step="any"
                           name="cells[<?= $col_id ?>]"
                           class="form-control"
                           value="<?= e($current_val ?? '') ?>">

                <?php else: /* text */ ?>
                    <input type="text"
                           name="cells[<?= $col_id ?>]"
                           class="form-control"
                           maxlength="255"
                           value="<?= e($current_val ?? '') ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
                <a href="/member/lists/<?= (int)$list['id'] ?>" class="btn btn-outline-secondary min-touch">
                    Abbrechen
                </a>
            </div>

            <?php endif; ?>
        </form>
    </div>
</div>
