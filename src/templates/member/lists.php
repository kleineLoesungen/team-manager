<?php
// src/templates/member/lists.php — overview for member (public + protected lists and files)
// Variables: $items (merged sorted array)

$visible = array_filter($items, fn($i) => !$i['is_hidden']);
$hidden  = array_filter($items, fn($i) =>  $i['is_hidden']);
?>

<?php
$render_card = function(array $item): void {
    $is_file    = ($item['type'] === 'file');
    $detail_url = $is_file ? '/member/files/' . (int)$item['id']
                           : '/member/lists/'  . (int)$item['id'];
    $icon = $is_file ? 'bi-file-earmark-text' : 'bi-table';
    ?>
<div class="col">
    <div class="card h-100 shadow-sm">
        <div class="card-header">
            <span class="fw-semibold">
                <i class="bi <?= $icon ?> me-1 text-muted"></i><?= e($item['name']) ?>
            </span>
            <?php if ($item['visibility'] === 'protected'): ?>
            <span class="badge bg-secondary ms-2">Nur lesen</span>
            <?php else: ?>
            <span class="badge bg-success ms-2">Öffentlich</span>
            <?php endif; ?>
        </div>
        <?php if ($item['date']): ?>
        <div class="card-body py-2 px-3">
            <small class="text-muted">
                <i class="bi bi-calendar3 me-1"></i><?= (new DateTime($item['date']))->format('d.m.Y') ?>
            </small>
        </div>
        <?php endif; ?>
        <div class="card-footer bg-transparent">
            <a href="<?= $detail_url ?>" class="btn btn-sm btn-primary min-touch">
                <i class="bi bi-box-arrow-in-right me-1"></i>Öffnen
            </a>
        </div>
    </div>
</div>
<?php }; ?>

<?php if (empty($items)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Keine Einträge verfügbar</p>
    <p class="text-muted">Dein Koordinator hat noch keine Listen oder Dateien angelegt.</p>
</div>

<?php else: ?>

<?php if (!empty($visible)): ?>
<div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
    <?php foreach ($visible as $item): $render_card($item); endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($hidden)): ?>
<div class="mt-2">
    <button class="btn btn-outline-secondary btn-sm w-100 d-flex justify-content-between align-items-center"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#hiddenItems"
            aria-expanded="false"
            aria-controls="hiddenItems">
        <span class="text-muted">
            <i class="bi bi-eye-slash me-1"></i>Ältere Einträge (<?= count($hidden) ?>)
        </span>
        <i class="bi bi-chevron-down"></i>
    </button>
    <div class="collapse" id="hiddenItems">
        <div class="row row-cols-1 row-cols-md-2 g-3 mt-1">
            <?php foreach ($hidden as $item): $render_card($item); endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
