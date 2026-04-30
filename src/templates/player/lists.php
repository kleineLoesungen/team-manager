<?php
// src/templates/player/lists.php — List overview for player (public + protected)
// Variables: $lists (array of list rows)
?>

<?php if (empty($lists)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Keine Listen verfügbar</p>
    <p class="text-muted">Ihr Trainer hat noch keine Listen angelegt.</p>
</div>

<?php else: ?>
<div class="row row-cols-1 row-cols-md-2 g-3">
    <?php foreach ($lists as $list): ?>
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-header">
                <span class="fw-semibold"><?= e($list['name']) ?></span>
                <?php if ($list['visibility'] === 'protected'): ?>
                <span class="badge bg-secondary ms-2">Nur lesen</span>
                <?php else: ?>
                <span class="badge bg-success ms-2">Öffentlich</span>
                <?php endif; ?>
            </div>

            <div class="card-footer bg-transparent">
                <a href="/player/lists/<?= (int)$list['id'] ?>" class="btn btn-sm btn-primary min-touch">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Öffnen
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
