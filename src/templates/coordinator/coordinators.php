<?php declare(strict_types=1); ?>
<?php if (empty($coordinators)): ?>
    <p class="text-muted">Keine aktiven Koordinatoren in diesem Team.</p>
<?php else: ?>
    <p class="text-muted mb-3"><?= count($coordinators) ?> Koordinator<?= count($coordinators) !== 1 ? 'en' : '' ?> in diesem Team</p>
    <div class="list-group">
        <?php foreach ($coordinators as $c): ?>
            <div class="list-group-item">
                <div class="fw-semibold"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></div>
                <?php if (!empty($c['phone'])): ?>
                    <div class="text-muted small">
                        <i class="bi bi-telephone me-1"></i>
                        <a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">Keine Telefonnummer hinterlegt</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
