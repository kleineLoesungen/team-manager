<?php
// src/templates/coordinator/columns.php — Global columns overview (LIST-02)
// Variables: $columns (array of team global column rows), $system_columns (array of system column rows)
?>
<div class="mb-3">
    <a href="/coordinator/settings" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Einstellungen
    </a>
</div>

<?php if (!empty($system_columns)): ?>
<h6 class="text-muted fw-semibold mb-2">
    <i class="bi bi-lock-fill me-1"></i>Systemspalten <span class="fw-normal">(vom Admin verwaltet)</span>
</h6>
<div class="table-responsive mb-4">
    <table class="table table-sm align-middle">
        <tbody>
            <?php foreach ($system_columns as $col): ?>
            <tr class="text-muted">
                <td><?= e($col['name']) ?></td>
                <td>
                    <span class="badge bg-light text-dark border">
                        <?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>
                    </span>
                </td>
                <td class="text-end">
                    <span class="badge bg-secondary-subtle text-secondary">
                        <i class="bi bi-lock me-1"></i>Systemspalte
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="text-muted fw-semibold mb-0">Team-Spalten</h6>
    <span class="text-muted small"><?= count($columns) ?> <?= count($columns) === 1 ? 'Spalte' : 'Spalten' ?></span>
</div>

<?php if (empty($columns)): ?>
<div class="text-center py-4 text-muted mb-4">
    <p class="mb-0">Noch keine eigenen globalen Spalten für dieses Team.</p>
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
    <div class="card-header fw-semibold">Neue Team-Spalte anlegen</div>
    <div class="card-body">
        <form method="POST" action="/coordinator/columns/create">
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

<div class="mt-4">
    <a href="/coordinator/settings" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Einstellungen
    </a>
</div>
