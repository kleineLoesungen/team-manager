<?php
// src/templates/admin/clubs.php — Admin clubs list
// Variables: $active_clubs (array), $inactive_clubs (array), $error (string)
?>
<?php if (!empty($error)): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Aktion erfolgreich.'); ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_clubs) ?> aktiver Klub<?= count($active_clubs) !== 1 ? 's' : '' ?></span>
    <a href="/admin/clubs/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Klub hinzufügen
    </a>
</div>

<?php if (empty($active_clubs) && empty($inactive_clubs)): ?>
<?php render_empty('building', 'Noch keine Klubs', 'Erstelle den ersten Klub, um Mitglieder Vereinen zuzuordnen.',
    '<a href="/admin/clubs/create" class="btn btn-outline-primary mt-3">Klub hinzufügen</a>'); ?>
<?php else: ?>

<?php render_collection_group('Aktiv', function() use ($active_clubs) {
    if (empty($active_clubs)) {
        echo '<p class="text-muted small mb-3">Keine aktiven Klubs vorhanden.</p>';
        return;
    }
    ?>
    <div class="list-group mb-4">
        <?php foreach ($active_clubs as $club): ?>
        <div class="list-group-item px-3 py-3">
            <div class="fw-semibold mb-2"><?= e($club['name']) ?></div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/admin/clubs/<?= (int)$club['id'] ?>/edit" data-save-scroll
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Bearbeiten
                </a>
                <form method="POST" action="/admin/clubs/<?= (int)$club['id'] ?>/deactivate">
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

<?php if (!empty($inactive_clubs)): ?>
<div class="mt-4">
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            type="button" data-bs-toggle="collapse" data-bs-target="#inactiveClubs" aria-expanded="false">
        <i class="bi bi-chevron-down"></i>
        Inaktiv (<?= count($inactive_clubs) ?>)
    </button>
    <div class="collapse mt-2" id="inactiveClubs">
        <?php render_collection_group('Inaktiv', function() use ($inactive_clubs) { ?>
        <div class="list-group opacity-75">
            <?php foreach ($inactive_clubs as $club): ?>
            <div class="list-group-item px-3 py-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-semibold text-muted"><?= e($club['name']) ?></span>
                    <?php render_badge('dim', 'Inaktiv'); ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="/admin/clubs/<?= (int)$club['id'] ?>/reactivate">
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
