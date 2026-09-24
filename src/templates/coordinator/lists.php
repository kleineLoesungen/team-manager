<?php
// src/templates/coordinator/lists.php — overview tabs: Kalender (default) | Liste
// Variables: $items, $view, $showCalendar, $periodView, $offset, $boundaries,
//            $datedItems, $undatedItems, $ics_url

// ── German day name helper ────────────────────────────────────────────────────
$de_days = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
$day_header = function(string $date) use ($de_days): string {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $de_days[(int)$dt->format('N') - 1] . ', ' . $dt->format('d.m.Y');
};

// ── URL builder helpers ───────────────────────────────────────────────────────
$base_url  = '/coordinator/lists';
$cal_url   = fn(string $v, int $off) => $base_url . '?view=' . urlencode($v) . '&offset=' . $off;

// ── Visibility badge helper ───────────────────────────────────────────────────
$vis_badge = function(string $visibility): void {
    $type  = match($visibility) { 'public' => 'ok', 'protected' => 'warn', 'private' => 'dim', default => 'dim' };
    $label = match($visibility) { 'public' => 'Öffentlich', 'protected' => 'Geschützt', 'private' => 'Privat', default => htmlspecialchars($visibility, ENT_QUOTES) };
    render_badge($type, $label);
};
?>

<?php if ($_GET['success'] ?? null): render_flash('success', 'Gespeichert.'); endif; ?>

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
     CALENDAR VIEW (D-01, D-02, D-03, D-04, D-05, D-09)
     ════════════════════════════════════════════════════════════════════════════ -->

<!-- Add button row (same as list view) -->
<div class="d-flex justify-content-end mb-3">
    <div class="dropdown">
        <button class="btn btn-primary btn-sm dropdown-toggle" type="button"
                data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-plus-lg me-1"></i>Neu
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item" href="/coordinator/lists/create">
                    <i class="bi bi-people me-2"></i>Mitgliederliste
                </a>
            </li>
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
            <li>
                <a class="dropdown-item" href="/coordinator/events/create">
                    <i class="bi bi-calendar-event me-2"></i>Termin
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Week/Month toggle ───────────────────────────────────────────────── -->
<div class="seg-ctrl">
    <a href="<?= $cal_url('week', 0) ?>" class="<?= $periodView === 'week' ? 'on' : '' ?>">Woche</a>
    <a href="<?= $cal_url('month', 0) ?>" class="<?= $periodView === 'month' ? 'on' : '' ?>">Monat</a>
</div>

<!-- Period navigation: ‹ label › ────────────────────────────────────── -->
<div class="period-nav">
    <a href="<?= $cal_url($periodView, $offset - 1) ?>" title="<?= $periodView === 'week' ? 'Vorherige Woche' : 'Vorheriger Monat' ?>">‹</a>
    <span class="period-label"><?= e($boundaries['label']) ?></span>
    <a href="<?= $cal_url($periodView, $offset + 1) ?>" title="<?= $periodView === 'week' ? 'Nächste Woche' : 'Nächster Monat' ?>">›</a>
</div>

<!-- Dated entries timeline (D-04, D-05) -->
<?php if (!empty($datedItems)): ?>
<div class="mb-4">
    <?php
    // Group items by date, preserving order
    $groups_by_date = [];
    foreach ($datedItems as $_item) {
        $groups_by_date[$_item['date']][] = $_item;
    }
    foreach ($groups_by_date as $date => $group_items): ?>
    <div class="mb-3">
    <?php render_collection_group($day_header($date), function() use ($group_items, $vis_badge): void {
        foreach ($group_items as $item) {
            if ($item['type'] === 'event') { ?>
        <div class="card card-sm mb-2 border-0 bg-body-secondary">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1 min-w-0">
                        <a href="/coordinator/events/<?= (int)$item['id'] ?>/edit"
                           class="text-decoration-none fw-semibold text-body">
                            <i class="bi <?= e($item['icon'] ?? 'bi-calendar-event') ?> me-1 text-muted"></i><?= e($item['name']) ?>
                        </a>
                        <?php if (empty($item['is_all_day']) && !empty($item['time_start'])): ?>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-clock me-1"></i><?= e(substr((string)$item['time_start'], 0, 5)) ?><?php if (!empty($item['time_end'])): ?> – <?= e(substr((string)$item['time_end'], 0, 5)) ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($item['location'])): ?>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-geo-alt me-1"></i><?= e($item['location']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($item['visibility'] === 'private'): ?>
                    <?php render_badge('dim', 'Privat'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
            <?php } else {
                $is_file    = ($item['type'] === 'file');
                $detail_url = $is_file
                    ? '/coordinator/files/' . (int)$item['id']
                    : '/coordinator/lists/' . (int)$item['id']; ?>
        <div class="card card-sm mb-2">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1 min-w-0">
                        <a href="<?= e($detail_url) ?>" class="text-decoration-none fw-semibold text-body">
                            <i class="bi <?= $is_file ? 'bi-file-earmark-text' : 'bi-table' ?> me-1 text-muted"></i><?= e($item['name']) ?>
                        </a>
                        <?php if (!empty($item['location'])): ?>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-geo-alt me-1"></i><?= e($item['location']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($item['time_start'])): ?>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-clock me-1"></i><?= e(substr((string)$item['time_start'], 0, 5)) ?><?php if (!empty($item['time_end'])): ?> – <?= e(substr((string)$item['time_end'], 0, 5)) ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php $vis_badge($item['visibility']); ?>
                </div>
            </div>
        </div>
            <?php }
        }
    }); ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<?php render_empty('calendar3', 'Noch keine Einträge', 'Kein Eintrag mit Datum in diesem Zeitraum.'); ?>
<?php endif; ?>

<!-- Undated section (D-04) -->
<?php if (!empty($undatedItems)): ?>
<div class="mt-4">
<?php render_collection_group('Ohne Datum', function() use ($undatedItems, $vis_badge): void {
    foreach ($undatedItems as $item) {
        $is_file    = ($item['type'] === 'file');
        $detail_url = $is_file
            ? '/coordinator/files/' . (int)$item['id']
            : '/coordinator/lists/' . (int)$item['id']; ?>
    <div class="card card-sm mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1 min-w-0">
                    <a href="<?= e($detail_url) ?>" class="text-decoration-none fw-semibold text-body">
                        <i class="bi <?= $is_file ? 'bi-file-earmark-text' : 'bi-table' ?> me-1 text-muted"></i><?= e($item['name']) ?>
                    </a>
                </div>
                <?php $vis_badge($item['visibility']); ?>
            </div>
        </div>
    </div>
    <?php }
}); ?>
</div>
<?php endif; ?>

<!-- ICS info box (D-14) — bottom of calendar tab -->
<div class="alert alert-info py-2 mt-4 small d-flex align-items-center gap-2">
    <i class="bi bi-calendar2-check flex-shrink-0"></i>
    <span>
        <strong>Kalender abonnieren:</strong>
        Deinen persönlichen Kalender-Link findest du unter
        <a href="/coordinator/profile" class="alert-link">Mein Profil</a>.
    </span>
</div>

<?php else: ?>
<!-- ════════════════════════════════════════════════════════════════════════════
     LIST VIEW — existing card layout preserved exactly (D-06 "Liste" tab)
     ════════════════════════════════════════════════════════════════════════════ -->

<?php
$visible = array_filter($items, fn($i) => !$i['is_hidden']);
$hidden  = array_filter($items, fn($i) =>  $i['is_hidden']);

$render_card = function(array $item) use ($vis_badge): void {
    $is_event = ($item['type'] === 'event');
    $is_file  = ($item['type'] === 'file');

    if ($is_event) {
        $detail_url   = '/coordinator/events/' . (int)$item['id'] . '/edit';
        $settings_url = null;
        $icon         = $item['icon'] ?? 'bi-calendar-event';
    } else {
        $detail_url   = $is_file ? '/coordinator/files/' . (int)$item['id']
                                 : '/coordinator/lists/'  . (int)$item['id'];
        $settings_url = $is_file ? null : '/coordinator/lists/' . (int)$item['id'] . '/settings';
        $icon         = $is_file ? 'bi-file-earmark-text' : 'bi-table';
    }
    ?>
<div class="col">
    <div class="card h-100 <?= $is_event ? 'border-0 bg-body-secondary' : '' ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi <?= $icon ?> me-1 text-muted"></i><?= e($item['name']) ?>
            </span>
            <?php if ($is_event): ?>
            <?php if ($item['visibility'] === 'private'): ?>
            <?php render_badge('dim', 'Privat'); ?>
            <?php else: ?>
            <?php render_badge('info', 'Mitglieder'); ?>
            <?php endif; ?>
            <?php else: ?>
            <?php $vis_badge($item['visibility']); ?>
            <?php endif; ?>
        </div>
        <?php if ($item['date']): ?>
        <div class="card-body py-2 px-3">
            <small class="text-muted">
                <i class="bi bi-calendar3 me-1"></i><?= (new DateTime($item['date']))->format('d.m.Y') ?>
                <?php $show_time = !$is_event || empty($item['is_all_day']); ?>
                <?php if ($show_time && !empty($item['time_start'])): ?>
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
        <?php elseif (!$is_event && !empty($item['location'])): ?>
        <div class="card-body py-2 px-3">
            <small class="text-muted">
                <i class="bi bi-geo-alt me-1"></i><?= e($item['location']) ?>
            </small>
        </div>
        <?php endif; ?>
        <div class="card-footer bg-transparent d-flex gap-2">
            <a href="<?= $detail_url ?>" class="btn btn-sm <?= $is_event ? 'btn-outline-secondary' : 'btn-primary' ?> min-touch">
                <i class="bi bi-<?= $is_event ? 'pencil' : 'box-arrow-in-right' ?> me-1"></i><?= $is_event ? 'Bearbeiten' : 'Öffnen' ?>
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($items) ?> <?= count($items) === 1 ? 'Eintrag' : 'Einträge' ?></span>
    <div class="dropdown">
        <button class="btn btn-primary btn-sm dropdown-toggle" type="button"
                data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-plus-lg me-1"></i>Neu
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item" href="/coordinator/lists/create">
                    <i class="bi bi-people me-2"></i>Mitgliederliste
                </a>
            </li>
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
            <li>
                <a class="dropdown-item" href="/coordinator/events/create">
                    <i class="bi bi-calendar-event me-2"></i>Termin
                </a>
            </li>
        </ul>
    </div>
</div>

<?php if (empty($items)): ?>
<?php render_empty('collection', 'Noch keine Einträge', 'Lege die erste Liste oder Datei an.'); ?>

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
            <i class="bi bi-eye-slash me-1"></i>Versteckte Einträge (<?= count($hidden) ?>)
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
<script>
(function() {
    var url = (location.pathname + location.search).replace(/[?&]success=1/, '').replace(/\?$/, '');
    if (url !== location.pathname + location.search) history.replaceState(null, '', url);
    sessionStorage.setItem('coordinator_lists_url', url);
    var saved = sessionStorage.getItem('coordinator_lists_scroll');
    if (saved !== null) {
        sessionStorage.removeItem('coordinator_lists_scroll');
        window.scrollTo(0, parseInt(saved, 10));
    }
    window.addEventListener('beforeunload', function() {
        sessionStorage.setItem('coordinator_lists_scroll', window.scrollY);
    });
})();
</script>
