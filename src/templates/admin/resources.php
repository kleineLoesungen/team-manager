<?php
// src/templates/admin/resources.php — Admin: Ressourcen für alle Teams
// Variables: $active, $inactive (arrays with id, name, department_name, bookings), $error, $success,
//            $departments (departments_list()), $department (?int filter)
?>
<?php if ($error !== ''): render_flash('error', $error); endif; ?>
<?php if ($success !== ''): render_flash('success', $success); endif; ?>

<?php render_page_header('Ressourcen', '/admin/settings'); ?>

<p class="text-muted small mb-3">
    Plätze, Hallen, Busse … Jede Ressource gehört zu einer Abteilung; deren Teams wählen sie bei
    Listen und Terminen aus und sehen die Auslastung.
</p>

<form method="POST" action="/admin/resources" class="d-grid gap-2 mb-4">
    <?= csrf_field() ?>
    <label for="resource_name" class="form-label fw-semibold mb-0">Neue Ressource</label>
    <input type="text" id="resource_name" name="name" class="form-control" maxlength="100" required
           placeholder="z. B. Kunstrasen Platz 1">
    <?php render_department_select($departments, $department); ?>
    <button type="submit" class="btn btn-primary min-touch">Ressource hinzufügen</button>
</form>

<?php render_department_filter($departments, $department, '/admin/resources'); ?>

<?php if (!$active && !$inactive): ?>
<?php render_empty('box-seam', 'Noch keine Ressourcen', 'Lege die erste Ressource an, damit Teams sie belegen können.'); ?>
<?php else: ?>

<?php if ($active): render_collection_group('Aktiv', function () use ($active) { ?>
<div class="list-group mb-4">
    <?php foreach ($active as $r): ?>
    <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
        <span class="flex-grow-1 min-w-0">
            <span class="d-block fw-semibold"><?= e($r['name']) ?></span>
            <span class="d-block small text-muted"><?= e($r['department_name']) ?> · <?= (int)$r['bookings'] ?> Belegung<?= (int)$r['bookings'] === 1 ? '' : 'en' ?></span>
        </span>
        <a href="/admin/resources/<?= (int)$r['id'] ?>/edit" data-save-scroll class="btn btn-sm btn-outline-secondary min-touch">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Bearbeiten
        </a>
        <form method="POST" action="/admin/resources/<?= (int)$r['id'] ?>/deactivate">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-warning min-touch">
                <i class="bi bi-pause-circle me-1" aria-hidden="true"></i>Deaktivieren
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php }); endif; ?>

<?php if ($inactive): render_collection_group('Deaktiviert', function () use ($inactive) { ?>
<p class="text-muted small mb-2">Nicht auswählbar und nicht in der Auslastung. Belegungen bleiben gespeichert.</p>
<div class="list-group">
    <?php foreach ($inactive as $r): ?>
    <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
        <span class="flex-grow-1 min-w-0">
            <span class="d-block fw-semibold text-muted"><?= e($r['name']) ?></span>
            <span class="d-block small text-muted"><?= e($r['department_name']) ?></span>
        </span>
        <?php render_badge('dim', 'Deaktiviert'); ?>
        <form method="POST" action="/admin/resources/<?= (int)$r['id'] ?>/reactivate">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-success min-touch">
                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Reaktivieren
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php }); endif; ?>

<?php endif; ?>
