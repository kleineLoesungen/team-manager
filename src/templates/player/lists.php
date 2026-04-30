<?php
// src/templates/player/lists.php — Public list overview for player (D-13)
// Variables: $lists (array of public list rows)
?>

<?php if (empty($lists)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Keine Listen verfügbar</p>
    <p class="text-muted">Ihr Trainer hat noch keine öffentlichen Listen angelegt.</p>
</div>

<?php else: ?>
<div class="row row-cols-1 row-cols-md-2 g-3">
    <?php foreach ($lists as $list): ?>
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-header">
                <span class="fw-semibold"><?= e($list['name']) ?></span>
                <span class="badge bg-success ms-2">Öffentlich</span>
            </div>
            <div class="card-body">
                <p class="card-text text-muted small">
                    <i class="bi bi-layout-three-columns me-1"></i><?= (int)$list['column_count'] ?> Spalten
                </p>
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
