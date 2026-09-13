<?php declare(strict_types=1); ?>
<div class="mb-3">
    <a href="/coordinator/profile" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Profil
    </a>
</div>

<?php if (empty($teams_map)): ?>
<?php render_empty('people', 'Keine Koordinatoren', 'Für dieses Team sind aktuell keine Koordinatoren zugewiesen.'); ?>
<?php else: ?>
    <?php foreach ($teams_map as $team): ?>
    <?php render_collection_group($team['team_name'], function() use ($team): void { ?>
    <div class="list-group mb-4">
        <?php foreach ($team['coordinators'] as $c): ?>
        <div class="list-group-item">
            <div class="fw-semibold"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></div>
            <?php if (!empty($c['club_name'])): ?>
            <div class="text-muted small mb-1">
                <i class="bi bi-building me-1"></i><?= e($c['club_name']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($c['phone'])): ?>
            <div class="text-muted small">
                <i class="bi bi-telephone me-1"></i>
                <a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a>
            </div>
            <?php else: ?>
            <div class="text-muted small">Keine Telefonnummer hinterlegt</div>
            <?php endif; ?>
            <?php if (!empty($c['email'])): ?>
            <div class="text-muted small">
                <i class="bi bi-envelope me-1"></i>
                <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php }); ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="mt-4">
    <a href="/coordinator/profile" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Profil
    </a>
</div>
