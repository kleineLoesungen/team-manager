<?php
// src/templates/admin/clubs.php — Admin clubs list
// Variables: $active_clubs (array), $inactive_clubs (array), $error (string)
?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>
<?php if (!empty($_GET['success'])): ?>
<div class="alert alert-success"><?= e($_GET['success']) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_clubs) ?> aktiver Klub<?= count($active_clubs) !== 1 ? 's' : '' ?></span>
    <a href="/admin/clubs/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Klub hinzufügen
    </a>
</div>

<?php if (empty($active_clubs) && empty($inactive_clubs)): ?>
<div class="alert alert-info">
    Noch keine Klubs vorhanden. <a href="/admin/clubs/create" class="alert-link">Ersten Klub anlegen</a>.
</div>
<?php else: ?>

<?php if (!empty($active_clubs)): ?>
<div class="list-group">
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
                <button type="submit" class="btn btn-sm btn-outline-danger">Deaktivieren</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-secondary">Noch keine aktiven Klubs vorhanden.</div>
<?php endif; ?>

<?php if (!empty($inactive_clubs)): ?>
<details class="mt-4">
    <summary class="text-muted small mb-3" style="cursor:pointer;list-style:none;">
        <i class="bi bi-chevron-right me-1"></i>Inaktiv (<?= count($inactive_clubs) ?>)
    </summary>
    <div class="list-group mt-2 opacity-75">
        <?php foreach ($inactive_clubs as $club): ?>
        <div class="list-group-item px-3 py-3">
            <div class="fw-semibold text-muted mb-1"><?= e($club['name']) ?></div>
            <span class="badge bg-secondary mb-2">Deaktiviert</span>
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
</details>
<?php endif; ?>

<?php endif; ?>
