<?php
// src/templates/coordinator/lists.php — overview tabs: Kalender (default) | Liste
// Variables: $items, $view, $showCalendar, $periodView, $offset, $boundaries,
//            $datedItems, $undatedItems, $ics_url

// ── Shared badge helpers ──────────────────────────────────────────────────────
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

// ── German day name helper ────────────────────────────────────────────────────
$de_days = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
$day_header = function(string $date) use ($de_days): string {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $de_days[(int)$dt->format('N') - 1] . ', ' . $dt->format('d.m.Y');
};

// ── URL builder helpers ───────────────────────────────────────────────────────
$base_url  = '/coordinator/lists';
$cal_url   = fn(string $v, int $off) => $base_url . '?view=' . urlencode($v) . '&offset=' . $off;
?>

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
    $currentDate = null;
    foreach ($datedItems as $item):
        $itemDate = $item['date'];
        if ($itemDate !== $currentDate):
            if ($currentDate !== null): echo '</div>'; endif;
            $currentDate = $itemDate;
    ?>
    <div class="mb-3">
        <h6 class="text-muted border-bottom pb-1 mb-2">
            <?= e($day_header($itemDate)) ?>
        </h6>
    <?php endif; ?>

    <?php if ($item['type'] === 'event'): ?>
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
                <span class="badge bg-secondary flex-shrink-0">Privat</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <?php
    $is_file    = ($item['type'] === 'file');
    $detail_url = $is_file
        ? '/coordinator/files/' . (int)$item['id']
        : '/coordinator/lists/' . (int)$item['id'];
    ?>
    <div class="card card-sm mb-2 shadow-sm">
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
                <span class="badge <?= $badge_class($item['visibility']) ?> flex-shrink-0">
                    <?= $badge_label($item['visibility']) ?>
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($currentDate !== null): echo '</div>'; endif; ?>
</div>
<?php else: ?>
<div class="text-center py-4 text-muted">
    <i class="bi bi-calendar3 d-block mb-2" style="font-size:2rem;"></i>
    Noch keine Einträge mit Datum in diesem Zeitraum
</div>
<?php endif; ?>

<!-- Undated section (D-04) -->
<?php if (!empty($undatedItems)): ?>
<div class="mt-4">
    <h6 class="text-muted border-bottom pb-1 mb-3">Ohne Datum</h6>
    <?php foreach ($undatedItems as $item):
        $is_file    = ($item['type'] === 'file');
        $detail_url = $is_file
            ? '/coordinator/files/' . (int)$item['id']
            : '/coordinator/lists/' . (int)$item['id'];
    ?>
    <div class="card card-sm mb-2 shadow-sm">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1 min-w-0">
                    <a href="<?= e($detail_url) ?>" class="text-decoration-none fw-semibold text-body">
                        <i class="bi <?= $is_file ? 'bi-file-earmark-text' : 'bi-table' ?> me-1 text-muted"></i><?= e($item['name']) ?>
                    </a>
                </div>
                <span class="badge <?= $badge_class($item['visibility']) ?> flex-shrink-0">
                    <?= $badge_label($item['visibility']) ?>
                </span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ICS info box (D-14) — bottom of calendar tab -->
<?php if (!empty($ics_url)): ?>
<div class="alert alert-info py-2 mt-4 small">
    <strong>In Kalender-App abonnieren:</strong>
    Kopiere den Link um die Termine in deiner Kalender-App zu abonnieren.<br>
    <code class="user-select-all"><?= e($ics_url) ?></code>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ════════════════════════════════════════════════════════════════════════════
     LIST VIEW — existing card layout preserved exactly (D-06 "Liste" tab)
     ════════════════════════════════════════════════════════════════════════════ -->

<?php
$visible = array_filter($items, fn($i) => !$i['is_hidden']);
$hidden  = array_filter($items, fn($i) =>  $i['is_hidden']);

$render_card = function(array $item) use ($badge_class, $badge_label): void {
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
    <div class="card h-100 <?= $is_event ? 'border-0 bg-body-secondary' : 'shadow-sm' ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi <?= $icon ?> me-1 text-muted"></i><?= e($item['name']) ?>
            </span>
            <?php if ($is_event): ?>
            <?php if ($item['visibility'] === 'private'): ?>
            <span class="badge bg-secondary">Privat</span>
            <?php else: ?>
            <span class="badge bg-warning text-dark">Mitglieder</span>
            <?php endif; ?>
            <?php else: ?>
            <span class="badge <?= $badge_class($item['visibility']) ?>"><?= $badge_label($item['visibility']) ?></span>
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
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Einträge</p>
    <p class="text-muted">Lege die erste Liste oder Datei an.</p>
</div>

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
