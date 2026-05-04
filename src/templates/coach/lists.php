<?php
// src/templates/moderator/lists.php — List overview cards (LIST-01, LIST-04)
// Variables: $lists (array of list rows ordered is_hidden ASC, created_at DESC)
$visible = array_filter($lists, fn($l) => !$l['is_hidden']);
$hidden  = array_filter($lists, fn($l) =>  $l['is_hidden']);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($lists) ?> <?= count($lists) === 1 ? 'Liste' : 'Listen' ?></span>
    <a href="/moderator/lists/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Neue Liste anlegen
    </a>
</div>

<?php if (empty($lists)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Listen</p>
    <p class="text-muted">Lege die erste Liste an.</p>
</div>

<?php else: ?>

<?php
// Reusable card renderer
$render_card = function(array $list): void { ?>
<div class="col">
    <div class="card h-100 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?= e($list['name']) ?></span>
            <?php
                $badge_class = match($list['visibility']) {
                    'public'    => 'bg-success',
                    'protected' => 'bg-warning text-dark',
                    'private'   => 'bg-secondary',
                    default     => 'bg-secondary',
                };
                $badge_label = match($list['visibility']) {
                    'public'    => 'Öffentlich',
                    'protected' => 'Geschützt',
                    'private'   => 'Privat',
                    default     => e($list['visibility']),
                };
            ?>
            <span class="badge <?= $badge_class ?>"><?= $badge_label ?></span>
        </div>
        <div class="card-footer bg-transparent d-flex gap-2">
            <a href="/moderator/lists/<?= (int)$list['id'] ?>" class="btn btn-sm btn-primary min-touch">
                <i class="bi bi-box-arrow-in-right me-1"></i>Öffnen
            </a>
            <a href="/moderator/lists/<?= (int)$list['id'] ?>/settings" class="btn btn-sm btn-outline-secondary min-touch">
                <i class="bi bi-gear me-1"></i>Einstellungen
            </a>
        </div>
    </div>
</div>
<?php }; ?>

<?php if (!empty($visible)): ?>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
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
            <i class="bi bi-eye-slash me-1"></i>Versteckte Listen (<?= count($hidden) ?>)
        </span>
        <i class="bi bi-chevron-down"></i>
    </button>
    <div class="collapse" id="hiddenLists">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mt-1">
            <?php foreach ($hidden as $list): $render_card($list); endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
