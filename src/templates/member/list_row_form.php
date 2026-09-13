<?php
// src/templates/member/list_row_form.php — Member row edit form (CELL-01)
// Variables: $list (id, name), $player (id, first_name, last_name),
//            $columns, $existing_cells ([column_id => value])
?>
<?php if (isset($_GET['success'])): render_flash('success', 'Gespeichert.'); endif; ?>

<div class="mb-3">
    <a href="/member/lists/<?= (int)$list['id'] ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Liste
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span class="fw-semibold">
            <?= e($player['first_name'] . ' ' . $player['last_name']) ?>
        </span>
        <?php render_badge('info', 'Meine Zeile'); ?>
    </div>
    <div class="card-body">
        <form method="POST" id="row-form"
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
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="cells[<?= $col_id ?>]" value="1"
                               id="cell_<?= $col_id ?>"
                               <?= $current_val === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0" for="cell_<?= $col_id ?>">Ja</label>
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
                <a href="/member/lists/<?= (int)$list['id'] ?>" class="btn btn-outline-secondary min-touch">
                    Abbrechen
                </a>
            </div>

            <?php endif; ?>
        </form>
    </div>
</div>

<div class="mt-4">
    <a href="/member/lists/<?= (int)$list['id'] ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Liste
    </a>
</div>

<?php render_action_bar('Zeile speichern', 'row-form'); ?>
