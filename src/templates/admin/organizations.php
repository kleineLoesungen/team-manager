<?php
// src/templates/admin/organizations.php — Admin organizations list
// Variables: $active_organizations (array), $inactive_organizations (array), $error (string)
?>
<?php if (!empty($error)): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Aktion erfolgreich.'); ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_organizations) ?> aktive Organisation<?= count($active_organizations) !== 1 ? 'en' : '' ?></span>
    <a href="/admin/organizations/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Organisation hinzufügen
    </a>
</div>

<?php if (empty($active_organizations) && empty($inactive_organizations)): ?>
<?php render_empty('building', 'Noch keine Organisationen', 'Lege die erste Organisation an, um Mitglieder und Koordinatoren zuzuordnen.',
    '<a href="/admin/organizations/create" class="btn btn-outline-primary mt-3">Organisation hinzufügen</a>'); ?>
<?php else: ?>

<?php render_collection_group('Aktiv', function() use ($active_organizations) {
    if (empty($active_organizations)) {
        echo '<p class="text-muted small mb-3">Keine aktiven Organisationen vorhanden.</p>';
        return;
    }
    ?>
    <div class="list-group mb-4">
        <?php foreach ($active_organizations as $organization): ?>
        <div class="list-group-item">
            <div class="fw-semibold mb-2"><?= e($organization['name']) ?></div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/admin/organizations/<?= (int)$organization['id'] ?>/edit" data-save-scroll
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Bearbeiten
                </a>
                <form method="POST" action="/admin/organizations/<?= (int)$organization['id'] ?>/deactivate">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-pause-circle me-1"></i>Deaktivieren
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}); ?>

<?php if (!empty($inactive_organizations)): ?>
<div class="mt-4">
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            type="button" data-bs-toggle="collapse" data-bs-target="#inactiveOrganizations" aria-expanded="false">
        <i class="bi bi-chevron-down"></i>
        Inaktiv (<?= count($inactive_organizations) ?>)
    </button>
    <div class="collapse mt-2" id="inactiveOrganizations">
        <?php render_collection_group('Inaktiv', function() use ($inactive_organizations) { ?>
        <div class="list-group opacity-75">
            <?php foreach ($inactive_organizations as $organization): ?>
            <div class="list-group-item">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-semibold text-muted"><?= e($organization['name']) ?></span>
                    <?php render_badge('dim', 'Inaktiv'); ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="/admin/organizations/<?= (int)$organization['id'] ?>/reactivate">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php }); ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
