<?php
// src/templates/admin/column_merge_confirm.php
// Variables: $src, $target (arrays), $src_cells, $tgt_cells, $src_lists, $tgt_lists (int), $error (bool)
?>
<div class="mb-3">
    <a href="/admin/columns" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Abbrechen
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">Zusammenführen fehlgeschlagen. Bitte versuche es erneut.</div>
<?php endif; ?>

<div class="card border-warning mb-4">
    <div class="card-header fw-semibold" style="color: var(--warn); background: var(--warn-bg);">
        <i class="bi bi-arrow-left-right me-1"></i>Spalten zusammenführen
    </div>
    <div class="card-body">
        <p class="mb-3">
            <strong><?= e($src['name']) ?></strong> wird in <strong><?= e($target['name']) ?></strong> zusammengeführt.
            Alle Daten aus <strong><?= e($src['name']) ?></strong> werden übernommen,
            danach wird die Spalte <strong><?= e($src['name']) ?></strong> gelöscht.
        </p>

        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th></th>
                        <th class="text-danger"><i class="bi bi-trash me-1"></i>Wird gelöscht</th>
                        <th class="text-success"><i class="bi bi-check2 me-1"></i>Bleibt erhalten</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-muted">Name</td>
                        <td><?= e($src['name']) ?></td>
                        <td><?= e($target['name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Typ</td>
                        <td colspan="2"><?= $src['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Datenpunkte</td>
                        <td><?= $src_cells ?></td>
                        <td><?= $tgt_cells ?> → <?= $tgt_cells + $src_cells ?> nach Merge</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Listenzuordnungen</td>
                        <td><?= $src_lists ?></td>
                        <td><?= $tgt_lists ?> → <?= $tgt_lists + $src_lists ?> nach Merge</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-muted small mb-3">
            Diese Aktion kann nicht rückgängig gemacht werden.
        </p>

        <form method="POST" action="/admin/columns/<?= (int)$src['id'] ?>/merge/<?= (int)$target['id'] ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-arrow-left-right me-1"></i>Zusammenführen bestätigen
            </button>
        </form>
    </div>
</div>
