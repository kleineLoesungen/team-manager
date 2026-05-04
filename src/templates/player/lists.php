<?php
// src/templates/player/lists.php — List overview for player (public + protected)
// Variables: $lists (array of list rows ordered is_hidden ASC, created_at DESC)
$visible = array_filter($lists, fn($l) => !$l['is_hidden']);
$hidden  = array_filter($lists, fn($l) =>  $l['is_hidden']);
?>

<?php
$render_card = function(array $list): void { ?>
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
<?php }; ?>

<?php if (empty($lists)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Keine Listen verfügbar</p>
    <p class="text-muted">Dein Moderator hat noch keine Listen angelegt.</p>
</div>

<?php else: ?>

<?php if (!empty($visible)): ?>
<div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
    <?php foreach ($visible as $list): $render_card($list); endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($hidden)): ?>
<div class="mt-2">
    <button class="btn btn-outline-secondary btn-sm w-100 d-flex justify-content-between align-items-center"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#hiddenLists"
            aria-expanded="false"
            aria-controls="hiddenLists">
        <span class="text-muted">
            <i class="bi bi-eye-slash me-1"></i>Ältere Listen (<?= count($hidden) ?>)
        </span>
        <i class="bi bi-chevron-down"></i>
    </button>
    <div class="collapse" id="hiddenLists">
        <div class="row row-cols-1 row-cols-md-2 g-3 mt-1">
            <?php foreach ($hidden as $list): $render_card($list); endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
