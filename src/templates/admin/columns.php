<?php
// src/templates/admin/columns.php — Systemspalten list + create form
// Variables: $columns (array), $success (bool), $deleted (bool), $error (string)
?>
<div class="mb-3">
    <a href="/admin/settings" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Einstellungen
    </a>
</div>

<?php if ($error):   render_flash('error',   $error); endif; ?>
<?php if ($success): ?>
<?php if (!empty($_GET['promoted'])): ?>
<?php render_flash('success', 'Systemspalte erfolgreich angelegt. ' . (int)$_GET['promoted'] . ' bestehende Team-Spalte(n) wurden automatisch übernommen und zusammengeführt.'); ?>
<?php else: ?>
<?php render_flash('success', 'Systemspalte erfolgreich angelegt.'); ?>
<?php endif; ?>
<?php endif; ?>
<?php if ($deleted): render_flash('success', 'Systemspalte erfolgreich gelöscht.'); endif; ?>
<?php if (!empty($_GET['converted'])): render_flash('success', 'Systemspalte gelöscht. Daten wurden in ' . (int)$_GET['converted'] . ' teambezogene Koordinatorspalte(n) überführt.'); endif; ?>
<?php if (!empty($_GET['renamed'])): render_flash('success', 'Systemspalte erfolgreich umbenannt.'); endif; ?>
<?php if (!empty($_GET['merged'])): render_flash('success', 'Spalten erfolgreich zusammengeführt.'); endif; ?>

<h4 class="fw-semibold mb-2">Systemspalten</h4>
<p class="text-muted mb-3">
    Systemspalten sind teamübergreifend und können von Koordinatoren nur gelesen, nicht bearbeitet werden.
    Sie erscheinen auf der Mitglieder-Verlaufsseite als Hauptbereich.
</p>

<?php if (empty($columns)): ?>
<?php render_empty('table', 'Noch keine Systemspalten', 'Füge die erste globale Spalte hinzu.'); ?>
<?php else: ?>
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Typ</th>
                <th>Reihenfolge</th>
                <th>Erstellt</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($columns as $col): ?>
            <tr>
                <td><?= e($col['name']) ?></td>
                <td>
                    <?php render_badge('dim', $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl'); ?>
                </td>
                <td class="text-muted small"><?= (int)$col['sort_order'] ?></td>
                <td class="text-muted small"><?= e(date('d.m.Y', strtotime($col['created_at']))) ?></td>
                <td class="text-end">
                    <a href="/admin/columns/<?= (int)$col['id'] ?>/rename"
                       class="btn btn-outline-secondary btn-sm me-1">
                        <i class="bi bi-pencil me-1"></i>Umbenennen
                    </a>
                    <a href="/admin/columns/<?= (int)$col['id'] ?>/delete"
                       class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Löschen
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Create system column form -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Neue Systemspalte anlegen</div>
    <div class="card-body">
        <form method="POST" action="/admin/columns">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="mb-3">
                <label for="col_name" class="form-label">Name</label>
                <input type="text" id="col_name" name="name"
                       class="form-control" maxlength="100" required
                       placeholder="z.B. Einsatz">
            </div>
            <div class="mb-3">
                <label class="form-label">Typ</label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="data_type" id="type_boolean"
                               value="boolean" checked>
                        <label class="form-check-label" for="type_boolean">Ja/Nein</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="data_type" id="type_number"
                               value="number">
                        <label class="form-check-label" for="type_number">Zahl</label>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="sort_order" class="form-label">Reihenfolge</label>
                <input type="number" id="sort_order" name="sort_order"
                       class="form-control" value="0">
                <div class="form-text">Kleinere Zahlen erscheinen zuerst.</div>
            </div>
            <button type="submit" class="btn btn-primary">Spalte anlegen</button>
        </form>
    </div>
</div>

<!-- Gefahrenzone info -->
<div class="card border-danger mt-4">
    <div class="card-header text-danger fw-semibold">
        <i class="bi bi-exclamation-triangle me-1"></i>Gefahrenzone
    </div>
    <div class="card-body">
        <p class="text-muted mb-0 small">
            Beim Löschen einer Systemspalte, die noch von Teams genutzt wird, werden automatisch
            teambezogene Koordinatorspalten angelegt und alle vorhandenen Daten dort übernommen.
            Die Koordinatoren sehen ihre Daten weiterhin, können die Spalte aber selbst verwalten.
        </p>
    </div>
</div>

<div class="mt-4">
    <a href="/admin/settings" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Einstellungen
    </a>
</div>
