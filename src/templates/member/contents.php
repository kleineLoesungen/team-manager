<?php
// src/templates/member/contents.php — overview tabs: Übersicht (default) | Monat | Liste
// Variables: $items, $view, $showCalendar, $periodView, $offset, $boundaries,
//            $month, $ics_url, $dashboard, $can_create_events

// ── German day name helper ────────────────────────────────────────────────────
$de_days = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
$day_header = function(string $date) use ($de_days): string {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $de_days[(int)$dt->format('N') - 1] . ', ' . $dt->format('d.m.Y');
};

// ── URL helpers ───────────────────────────────────────────────────────────────
$base_url = '/member/contents';
$cal_url  = fn(string $v, int $off) => $base_url . '?view=' . urlencode($v) . '&offset=' . $off;
?>

<!-- ── View switcher: Übersicht / Monat / Liste ─────────────────────────── -->
<div class="seg-ctrl">
    <a href="<?= $base_url ?>" class="<?= ($view === 'overview') ? 'on' : '' ?>">
        <i class="bi bi-house me-1"></i>Übersicht
    </a>
    <a href="<?= $cal_url('month', 0) ?>" class="<?= ($view === 'month') ? 'on' : '' ?>">
        <i class="bi bi-calendar3 me-1"></i>Monat
    </a>
    <a href="<?= $base_url . '?view=list' ?>" class="<?= ($view === 'list') ? 'on' : '' ?>">
        <i class="bi bi-list-ul me-1"></i>Liste
    </a>
</div>

<?php if ($can_create_events): ?>
<div class="d-flex justify-content-end mb-3">
    <a href="/member/events/create" class="btn btn-primary btn-sm min-touch">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Termin anlegen
    </a>
</div>
<?php endif; ?>

<?php if ($view === 'overview'): ?>
<?php render_dashboard($dashboard, 'member'); ?>

<?php elseif ($showCalendar): ?>
<!-- ════════════════════════════════════════════════════════════════════════════
     CALENDAR VIEW
     ════════════════════════════════════════════════════════════════════════════ -->


<!-- Period navigation: ‹ label › -->
<div class="period-nav">
    <a href="<?= $cal_url($periodView, $offset - 1) ?>" title="Vorheriger Monat">‹</a>
    <span class="period-label"><?= e($boundaries['label']) ?></span>
    <a href="<?= $cal_url($periodView, $offset + 1) ?>" title="Nächster Monat">›</a>
</div>

<!-- Einträge des Monats — gleiche Zeilen wie die Übersicht (render_content_row) -->
<?php if (!empty($month['days'])): ?>
<div class="mb-4">
<?php foreach ($month['days'] as $_date => $_day_items):
    render_collection_group($day_header($_date), function () use ($_day_items, $month) { ?>
    <div class="list-group">
        <?php foreach ($_day_items as $_it) render_content_row($_it, 'member', $month['values']); ?>
    </div>
<?php });
endforeach; ?>
</div>
<?php else: ?>
<?php render_empty('calendar3', 'Keine Einträge', 'Noch keine Einträge mit Datum in diesem Monat.'); ?>
<?php endif; ?>

<!-- Ohne Datum: nicht versteckte Listen und Dokumente (versteckte stehen unter "Liste") -->
<?php if (!empty($month['undated'])): ?>
<div class="mt-4">
<?php render_collection_group('Ohne Datum', function () use ($month) { ?>
    <div class="list-group">
        <?php foreach ($month['undated'] as $_it) render_content_row($_it, 'member', $month['values']); ?>
    </div>
<?php }); ?>
</div>
<?php endif; ?>

<!-- Kalender-Abo — bottom of calendar tab -->
<?php if ($ics_url): ?>
<div class="card mt-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-calendar2-check"></i>
        <span class="fw-semibold">Kalender abonnieren</span>
    </div>
    <div class="card-body">
        <p class="text-body-secondary small mb-3">
            Alle Termine deines Teams in Apple Kalender, Google Calendar oder Outlook.
        </p>
        <div class="input-group mb-3">
            <input type="text" id="ics-url-lists" class="form-control font-monospace"
                   value="<?= e($ics_url) ?>" readonly>
            <button class="btn btn-outline-secondary" type="button" title="Link kopieren"
                    onclick="navigator.clipboard.writeText(document.getElementById('ics-url-lists').value).then(()=>{this.textContent='✓';setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard\'></i>';},1500)})">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>
        <a href="<?= e(webcal_url($ics_url)) ?>" class="btn btn-outline-primary min-touch">
            <i class="bi bi-calendar-plus me-1"></i>Kalender abonnieren
        </a>
    </div>
</div>
<?php endif; ?>

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
<script>
(function () {
    // Meldungen nur einmal: success/deleted/conflicts/error aus der Adresse entfernen
    var u = new URL(location.href);
    ['success', 'deleted', 'conflicts', 'error'].forEach(function (k) { u.searchParams.delete(k); });
    var url = u.pathname + u.search;
    if (url !== location.pathname + location.search) history.replaceState(null, '', url);
    sessionStorage.setItem('member_contents_url', url);
})();
</script>
