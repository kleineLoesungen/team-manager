<?php
// src/templates/admin/resources.php — Admin: Ressourcen für alle Teams
// Variables: $active, $inactive (arrays with id, name, bookings), $error, $success (strings)
?>
<?php if ($error !== ''): render_flash('error', $error); endif; ?>
<?php if ($success !== ''): render_flash('success', $success); endif; ?>

<?php render_page_header('Ressourcen', '/admin/settings'); ?>

<p class="text-muted small mb-3">
    Plätze, Hallen, Busse … Koordinatoren aller Teams wählen sie bei Listen und Terminen aus.
    Alle Mitglieder sehen die Auslastung.
</p>

<form method="POST" action="/admin/resources" class="d-grid gap-2 mb-4">
    <?= csrf_field() ?>
    <label for="resource_name" class="form-label fw-semibold mb-0">Neue Ressource</label>
    <input type="text" id="resource_name" name="name" class="form-control" maxlength="100" required
           placeholder="z. B. Kunstrasen Platz 1">
    <button type="submit" class="btn btn-primary min-touch">Ressource hinzufügen</button>
</form>

<?php if (!$active && !$inactive): ?>
<?php render_empty('box-seam', 'Noch keine Ressourcen', 'Lege die erste Ressource an, damit Teams sie belegen können.'); ?>
<?php else: ?>

<?php if ($active): render_collection_group('Aktiv', function () use ($active) { ?>
<div class="list-group mb-4">
    <?php foreach ($active as $r): ?>
    <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-semibold flex-grow-1"><?= e($r['name']) ?></span>
        <span class="small text-muted"><?= (int)$r['bookings'] ?> Belegung<?= (int)$r['bookings'] === 1 ? '' : 'en' ?></span>
        <a href="/admin/resources/<?= (int)$r['id'] ?>/edit" class="btn btn-sm btn-outline-secondary min-touch">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Umbenennen
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
        <span class="fw-semibold text-muted flex-grow-1"><?= e($r['name']) ?></span>
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
