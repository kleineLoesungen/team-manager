<?php
// src/templates/moderator/columns.php — Global columns overview (LIST-02)
// Variables: $columns (array of global column rows)
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($columns) ?> globale <?= count($columns) === 1 ? 'Spalte' : 'Spalten' ?></span>
</div>

<?php if (empty($columns)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine globalen Spalten</p>
    <p class="text-muted">Globale Spalten erscheinen in allen Listen Ihres Teams.</p>
</div>
<?php else: ?>
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Typ</th>
                <th>Erstellt</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($columns as $col): ?>
            <tr>
                <td><?= e($col['name']) ?></td>
                <td>
                    <span class="badge bg-light text-dark border">
                        <?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>
                    </span>
                </td>
                <td class="text-muted small"><?= e(date('d.m.Y', strtotime($col['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Create global column form (inline at bottom of page) -->
<div class="card shadow-sm" style="max-width: 500px;">
    <div class="card-header fw-semibold">Neue globale Spalte anlegen</div>
    <div class="card-body">
        <form method="POST" action="/moderator/columns/create">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="col_name" class="form-label">Name</label>
                <input type="text" id="col_name" name="name"
                       class="form-control" maxlength="100" required
                       placeholder="z. B. Tore, Gespielt">
            </div>
            <div class="mb-3">
                <label class="form-label">Typ</label>
                <select name="data_type" class="form-select">
                    <option value="boolean">Ja/Nein (boolean)</option>
                    <option value="number">Zahl (number)</option>
                </select>
                <div class="form-text">Text-Spalten sind nur in lokalen Listen-Spalten erlaubt.</div>
            </div>
            <button type="submit" class="btn btn-primary min-touch">Spalte anlegen</button>
        </form>
    </div>
</div>
