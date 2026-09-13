<?php
// src/templates/member/coordinators.php
// Variables: $coordinators (array), $other_teams (array — team_name => coordinator rows)
$other_teams ??= [];
?>
<?php if (isset($_GET['success'])): render_flash('success', 'Gespeichert.'); endif; ?>

<div class="mb-3">
    <a href="/member/profile" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<?php if (empty($coordinators)): ?>
<?php render_empty('person-badge', 'Keine Koordinatoren', 'Noch keine Koordinatoren in deinem Team.'); ?>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($coordinators as $c): ?>
    <div class="list-group-item">
        <div class="fw-semibold mb-1"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></div>
        <?php if (!empty($c['club_name'])): ?>
        <div class="text-muted small">
            <i class="bi bi-building me-1"></i><?= e($c['club_name']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($c['phone'])): ?>
        <div class="text-muted small">
            <i class="bi bi-telephone me-1"></i>
            <a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a>
        </div>
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
<?php endif; ?>

<?php foreach ($other_teams as $team_name => $members): ?>
<h2 class="h6 fw-semibold text-muted mt-4 mb-2"><?= e($team_name) ?></h2>
<div class="list-group mb-4">
    <?php foreach ($members as $c): ?>
    <div class="list-group-item">
        <div class="fw-semibold mb-1"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></div>
        <?php if (!empty($c['club_name'])): ?>
        <div class="text-muted small">
            <i class="bi bi-building me-1"></i><?= e($c['club_name']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($c['phone'])): ?>
        <div class="text-muted small">
            <i class="bi bi-telephone me-1"></i>
            <a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a>
        </div>
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
<?php endforeach; ?>
