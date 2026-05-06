<?php
// src/templates/coordinator/lists.php — overview cards (lists + files)
// Variables: $items (merged sorted array, each with 'type' = 'list'|'file')

$visible = array_filter($items, fn($i) => !$i['is_hidden']);
$hidden  = array_filter($items, fn($i) =>  $i['is_hidden']);

$badge_class = fn(string $v): string => match($v) {
    'public'    => 'bg-success',
    'protected' => 'bg-warning text-dark',
    'private'   => 'bg-secondary',
    default     => 'bg-secondary',
};
$badge_label = fn(string $v): string => match($v) {
    'public'    => 'Öffentlich',
    'protected' => 'Geschützt',
    'private'   => 'Privat',
    default     => e($v),
};
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($items) ?> <?= count($items) === 1 ? 'Eintrag' : 'Einträge' ?></span>

    <div class="btn-group">
        <a href="/coordinator/lists/create" class="btn btn-primary min-touch">
            <i class="bi bi-plus-lg me-1"></i>Mitgliederliste
        </a>
        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                data-bs-toggle="dropdown" aria-expanded="false">
            <span class="visually-hidden">Weitere Optionen</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item" href="/coordinator/lists/create?type=free">
                    <i class="bi bi-table me-2"></i>Freie Liste
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/coordinator/files/create">
                    <i class="bi bi-file-earmark-text me-2"></i>Datei
                </a>
            </li>
        </ul>
    </div>
</div>

<?php if (empty($items)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Einträge</p>
    <p class="text-muted">Lege die erste Liste oder Datei an.</p>
</div>

<?php else: ?>

<?php
$render_card = function(array $item) use ($badge_class, $badge_label): void {
    $is_file      = ($item['type'] === 'file');
    $detail_url   = $is_file ? '/coordinator/files/' . (int)$item['id']
                             : '/coordinator/lists/'  . (int)$item['id'];
    $settings_url = $is_file ? null
                             : '/coordinator/lists/'  . (int)$item['id'] . '/settings';
    $icon = $is_file ? 'bi-file-earmark-text' : 'bi-table';
    ?>
<div class="col">
    <div class="card h-100 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi <?= $icon ?> me-1 text-muted"></i><?= e($item['name']) ?>
            </span>
            <span class="badge <?= $badge_class($item['visibility']) ?>"><?= $badge_label($item['visibility']) ?></span>
        </div>
        <?php if ($item['date']): ?>
        <div class="card-body py-2 px-3">
            <small class="text-muted">
                <i class="bi bi-calendar3 me-1"></i><?= (new DateTime($item['date']))->format('d.m.Y') ?>
            </small>
        </div>
        <?php endif; ?>
        <div class="card-footer bg-transparent d-flex gap-2">
            <a href="<?= $detail_url ?>" class="btn btn-sm btn-primary min-touch">
                <i class="bi bi-box-arrow-in-right me-1"></i>Öffnen
            </a>
            <?php if ($settings_url): ?>
            <a href="<?= $settings_url ?>" class="btn btn-sm btn-outline-secondary min-touch">
                <i class="bi bi-gear me-1"></i>Einstellungen
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php }; ?>

<?php if (!empty($visible)): ?>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
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
            <i class="bi bi-eye-slash me-1"></i>Versteckte Einträge (<?= count($hidden) ?>)
        </span>
        <i class="bi bi-chevron-down"></i>
    </button>
    <div class="collapse" id="hiddenItems">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mt-1">
            <?php foreach ($hidden as $item): $render_card($item); endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
