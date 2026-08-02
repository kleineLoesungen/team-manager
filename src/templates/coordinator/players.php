<?php
// src/templates/coordinator/players.php — Player list for coordinator
declare(strict_types=1);
?>
<?php if (empty($players)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Spieler in diesem Team</p>
    <p class="text-muted small">Spieler werden vom Admin dem Team zugewiesen.</p>
</div>
<?php else: ?>
<p class="text-muted mb-3"><?= count($players) ?> Spieler in diesem Team</p>
<div class="list-group">
    <?php foreach ($players as $player): ?>
    <a href="/coordinator/players/<?= (int)$player['id'] ?>"
       class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-3">
        <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold"><?= e($player['last_name'] . ', ' . $player['first_name']) ?></div>
            <?php if (!empty($player['club_name'])): ?>
            <div class="text-muted small">
                <i class="bi bi-building me-1"></i><?= e($player['club_name']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($player['phone'])): ?>
            <div class="text-muted small">
                <i class="bi bi-telephone me-1"></i><?= e($player['phone']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($player['contact_name'])): ?>
            <div class="text-muted small">
                <i class="bi bi-person-lines-fill me-1"></i><?= e($player['contact_name']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="ms-2 flex-shrink-0 text-end">
            <?php if (!empty($player['linked_username'])): ?>
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                <i class="bi bi-link-45deg me-1"></i><?= e($player['linked_username']) ?>
            </span>
            <?php endif; ?>
            <i class="bi bi-chevron-right text-muted ms-2"></i>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
