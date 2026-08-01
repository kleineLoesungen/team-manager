<?php
// src/templates/admin/clubs.php — Admin clubs list
// Variables: $active_clubs (array), $inactive_clubs (array), $error (string)
?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
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
<div class="row g-3">
    <?php foreach ($active_clubs as $club): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <h2 class="h6 fw-semibold mb-0"><?= e($club['name']) ?></h2>
                    <div class="d-flex gap-2 flex-wrap align-items-start">
                        <!-- Inline edit form -->
                        <form method="POST" action="/admin/clubs/<?= (int)$club['id'] ?>/edit"
                              class="d-flex gap-2 align-items-center">
                            <?= csrf_field() ?>
                            <input type="text"
                                   class="form-control form-control-sm min-touch"
                                   name="name"
                                   value="<?= e($club['name']) ?>"
                                   maxlength="100"
                                   required
                                   style="min-width:140px">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Speichern</button>
                        </form>
                        <!-- Deactivate -->
                        <form method="POST" action="/admin/clubs/<?= (int)$club['id'] ?>/deactivate">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Deaktivieren</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-secondary">Noch keine aktiven Klubs vorhanden.</div>
<?php endif; ?>

<?php if (!empty($inactive_clubs)): ?>
<div class="mt-4">
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#inactiveClubs"
            aria-expanded="false">
        <i class="bi bi-chevron-down"></i>
        Inaktiv (<?= count($inactive_clubs) ?>)
    </button>
    <div class="collapse mt-2" id="inactiveClubs">
        <div class="row g-3">
            <?php foreach ($inactive_clubs as $club): ?>
            <div class="col-12">
                <div class="card border-secondary opacity-75">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                            <div>
                                <h2 class="h6 fw-semibold mb-0 text-muted"><?= e($club['name']) ?></h2>
                                <span class="badge bg-secondary mt-1">Deaktiviert</span>
                            </div>
                            <!-- Reactivate -->
                            <form method="POST" action="/admin/clubs/<?= (int)$club['id'] ?>/reactivate">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
