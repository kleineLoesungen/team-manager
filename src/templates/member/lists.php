<?php
// src/templates/member/lists.php — overview tabs: Kalender (default) | Liste
// Variables: $items, $view, $showCalendar, $periodView, $offset, $boundaries,
//            $datedItems, $undatedItems, $ics_url

// ── German day name helper ────────────────────────────────────────────────────
$de_days = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
$day_header = function(string $date) use ($de_days): string {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $de_days[(int)$dt->format('N') - 1] . ', ' . $dt->format('d.m.Y');
};

// ── URL helpers ───────────────────────────────────────────────────────────────
$base_url = '/member/lists';
$cal_url  = fn(string $v, int $off) => $base_url . '?view=' . urlencode($v) . '&offset=' . $off;
?>
<?php if (isset($_GET['success'])): render_flash('success', 'Gespeichert.'); endif; ?>

<!-- ── View switcher: Kalender / Liste ───────────────────────────────── -->
<div class="seg-ctrl">
    <a href="<?= $cal_url('calendar', 0) ?>" class="<?= ($view !== 'list') ? 'on' : '' ?>">
        <i class="bi bi-calendar3 me-1"></i>Kalender
    </a>
    <a href="<?= $base_url . '?view=list' ?>" class="<?= ($view === 'list') ? 'on' : '' ?>">
        <i class="bi bi-list-ul me-1"></i>Liste
    </a>
</div>

<?php if ($showCalendar): ?>
<!-- ════════════════════════════════════════════════════════════════════════════
     CALENDAR VIEW
     ════════════════════════════════════════════════════════════════════════════ -->

<!-- Week/Month toggle -->
<div class="seg-ctrl">
    <a href="<?= $cal_url('week', 0) ?>" class="<?= $periodView === 'week' ? 'on' : '' ?>">Woche</a>
    <a href="<?= $cal_url('month', 0) ?>" class="<?= $periodView === 'month' ? 'on' : '' ?>">Monat</a>
</div>

<!-- Period navigation: ‹ label › -->
<div class="period-nav">
    <a href="<?= $cal_url($periodView, $offset - 1) ?>" title="<?= $periodView === 'week' ? 'Vorherige Woche' : 'Vorheriger Monat' ?>">‹</a>
    <span class="period-label"><?= e($boundaries['label']) ?></span>
    <a href="<?= $cal_url($periodView, $offset + 1) ?>" title="<?= $periodView === 'week' ? 'Nächste Woche' : 'Nächster Monat' ?>">›</a>
</div>

<!-- Dated entries timeline -->
<?php if (!empty($datedItems)): ?>
<div class="mb-4">
<?php
$date_groups = [];
foreach ($datedItems as $_item) {
    $date_groups[$_item['date']][] = $_item;
}
foreach ($date_groups as $_date => $_group_items):
    render_collection_group($day_header($_date), function() use ($_group_items) {
        foreach ($_group_items as $_item):
            $_is_file    = ($_item['type'] === 'file');
            $_detail_url = $_is_file
                ? '/member/files/' . (int)$_item['id']
                : '/member/lists/' . (int)$_item['id'];
            ?>
            <div class="card card-sm mb-2">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <a href="<?= e($_detail_url) ?>" class="text-decoration-none fw-semibold text-body">
                                <i class="bi <?= $_is_file ? 'bi-file-earmark-text' : 'bi-table' ?> me-1 text-muted"></i><?= e($_item['name']) ?>
                            </a>
                            <?php if (!empty($_item['location'])): ?>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-geo-alt me-1"></i><?= e($_item['location']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($_item['time_start'])): ?>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-clock me-1"></i><?= e(substr((string)$_item['time_start'], 0, 5)) ?><?php if (!empty($_item['time_end'])): ?> – <?= e(substr((string)$_item['time_end'], 0, 5)) ?><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($_item['visibility'] === 'public'): render_badge('ok', 'Öffentlich');
                        else: render_badge('warn', 'Nur lesen'); endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach;
    });
endforeach;
?>
</div>
<?php else: ?>
<?php render_empty('calendar3', 'Keine Einträge', 'Noch keine Einträge mit Datum in diesem Zeitraum.'); ?>
<?php endif; ?>

<!-- Undated section -->
<?php if (!empty($undatedItems)): ?>
<?php render_collection_group('Ohne Datum', function() use ($undatedItems) {
    foreach ($undatedItems as $_item):
        $_is_file    = ($_item['type'] === 'file');
        $_detail_url = $_is_file
            ? '/member/files/' . (int)$_item['id']
            : '/member/lists/' . (int)$_item['id'];
    ?>
    <div class="card card-sm mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1 min-w-0">
                    <a href="<?= e($_detail_url) ?>" class="text-decoration-none fw-semibold text-body">
                        <i class="bi <?= $_is_file ? 'bi-file-earmark-text' : 'bi-table' ?> me-1 text-muted"></i><?= e($_item['name']) ?>
                    </a>
                </div>
                <?php if ($_item['visibility'] === 'public'): render_badge('ok', 'Öffentlich');
                else: render_badge('warn', 'Nur lesen'); endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach;
}); ?>
<?php endif; ?>

<!-- ICS info box — bottom of calendar tab -->
<div class="alert alert-info py-2 mt-4 small d-flex align-items-center gap-2">
    <i class="bi bi-calendar2-check flex-shrink-0"></i>
    <span>
        <strong>Kalender abonnieren:</strong>
        Deinen persönlichen Kalender-Link findest du unter
        <a href="/member/profile" class="alert-link">Mein Profil</a>.
    </span>
</div>

<?php else: ?>
<!-- ════════════════════════════════════════════════════════════════════════════
     LIST VIEW
     ════════════════════════════════════════════════════════════════════════════ -->

<?php
$visible = array_filter($items, fn($i) => !$i['is_hidden']);
$hidden  = array_filter($items, fn($i) =>  $i['is_hidden']);

$render_card = function(array $item): void {
    $is_file    = ($item['type'] === 'file');
    $detail_url = $is_file ? '/member/files/' . (int)$item['id']
                           : '/member/lists/'  . (int)$item['id'];
    $icon = $is_file ? 'bi-file-earmark-text' : 'bi-table';
    ?>
<div class="col">
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi <?= $icon ?> me-1 text-muted"></i><?= e($item['name']) ?>
            </span>
            <?php if ($item['visibility'] === 'public'): render_badge('ok', 'Öffentlich');
            else: render_badge('warn', 'Nur lesen'); endif; ?>
        </div>
        <?php if ($item['date']): ?>
        <div class="card-body py-2 px-3">
            <small class="text-muted">
                <i class="bi bi-calendar3 me-1"></i><?= (new DateTime($item['date']))->format('d.m.Y') ?>
                <?php if (!empty($item['time_start'])): ?>
                <span class="ms-2 text-muted small">
                    <i class="bi bi-clock me-1"></i><?= e(substr((string)$item['time_start'], 0, 5)) ?><?php if (!empty($item['time_end'])): ?> – <?= e(substr((string)$item['time_end'], 0, 5)) ?><?php endif; ?>
                </span>
                <?php endif; ?>
            </small>
            <?php if (!empty($item['location'])): ?>
            <small class="text-muted ms-3">
                <i class="bi bi-geo-alt me-1"></i><?= e($item['location']) ?>
            </small>
            <?php endif; ?>
        </div>
        <?php elseif (!empty($item['location'])): ?>
        <div class="card-body py-2 px-3">
            <small class="text-muted">
                <i class="bi bi-geo-alt me-1"></i><?= e($item['location']) ?>
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
<?php render_empty('collection', 'Keine Einträge', 'Dein Koordinator hat noch keine Listen oder Dateien angelegt.'); ?>

<?php else: ?>

<?php if (!empty($visible)): ?>
<div class="row row-cols-1 g-3 mb-4">
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
        <div class="row row-cols-1 g-3 mt-1">
            <?php foreach ($hidden as $item): $render_card($item); endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; // empty($items) ?>

<?php endif; // $showCalendar / list view ?>
<script>sessionStorage.setItem('member_lists_url', location.href);</script>
