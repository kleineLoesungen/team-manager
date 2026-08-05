<?php declare(strict_types=1); ?>
<?php if (empty($teams_map)): ?>
    <p class="text-muted">Keine aktiven Koordinatoren gefunden.</p>
<?php else: ?>
    <?php foreach ($teams_map as $team): ?>
    <div class="mb-4">
        <h2 class="h6 fw-semibold text-muted text-uppercase mb-2">
            <i class="bi bi-people me-1"></i><?= e($team['team_name']) ?>
        </h2>
        <div class="list-group">
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
    </div>
    <?php endforeach; ?>
<?php endif; ?>
