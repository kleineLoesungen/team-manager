<?php
// src/templates/admin/departments.php — Admin: Abteilungen
// Variables: $active, $inactive (arrays with id, name, teams, resources), $error, $success (strings)
?>
<?php if ($error !== ''): render_flash('error', $error); endif; ?>
<?php if ($success !== ''): render_flash('success', $success); endif; ?>

<?php render_page_header('Abteilungen', '/admin/settings'); ?>

<p class="text-muted small mb-3">
    Eine Abteilung (z. B. Fußball, Tennis) gruppiert Teams und Ressourcen. Teams sehen nur die
    Ressourcen ihrer Abteilung. Mitglieder und Organisationen gehören zu keiner Abteilung.
</p>

<form method="POST" action="/admin/departments" class="d-grid gap-2 mb-4">
    <?= csrf_field() ?>
    <label for="department_name" class="form-label fw-semibold mb-0">Neue Abteilung</label>
    <input type="text" id="department_name" name="name" class="form-control" maxlength="100" required
           placeholder="z. B. Fußball">
    <button type="submit" class="btn btn-primary min-touch">Abteilung hinzufügen</button>
</form>

<?php
$count = fn(array $d) => (int)$d['teams'] . ' Team' . ((int)$d['teams'] === 1 ? '' : 's')
                       . ' · ' . (int)$d['resources'] . ' Ressource' . ((int)$d['resources'] === 1 ? '' : 'n');
?>

<?php if ($active): render_collection_group('Aktiv', function () use ($active, $count) { ?>
<div class="list-group mb-4">
    <?php foreach ($active as $d): ?>
    <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
        <span class="flex-grow-1 min-w-0">
            <span class="d-block fw-semibold"><?= e($d['name']) ?></span>
            <span class="d-block small text-muted"><?= e($count($d)) ?></span>
        </span>
        <a href="/admin/departments/<?= (int)$d['id'] ?>/edit" class="btn btn-sm btn-outline-secondary min-touch">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Umbenennen
        </a>
        <form method="POST" action="/admin/departments/<?= (int)$d['id'] ?>/deactivate">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-warning min-touch">
                <i class="bi bi-pause-circle me-1" aria-hidden="true"></i>Deaktivieren
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php }); endif; ?>

<?php if ($inactive): render_collection_group('Deaktiviert', function () use ($inactive, $count) { ?>
<p class="text-muted small mb-2">Nicht auswählbar für neue Teams und Ressourcen. Bestehende behalten ihre Abteilung.</p>
<div class="list-group">
    <?php foreach ($inactive as $d): ?>
    <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
        <span class="flex-grow-1 min-w-0">
            <span class="d-block fw-semibold text-muted"><?= e($d['name']) ?></span>
            <span class="d-block small text-muted"><?= e($count($d)) ?></span>
        </span>
        <?php render_badge('dim', 'Deaktiviert'); ?>
        <form method="POST" action="/admin/departments/<?= (int)$d['id'] ?>/reactivate">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-success min-touch">
                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Reaktivieren
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php }); endif; ?>
