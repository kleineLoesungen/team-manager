<?php
// src/templates/public/guest_events.php — Gastbereich: Termine (Issue #15)
// Variables: $departments, $department (?int), $days ([Y-m-d => rows], guest_upcoming()),
//            $calendars (guest_calendars())
require_once dirname(__DIR__, 2) . '/templates/layout.php';
render_page(['title' => 'Termine', 'role' => 'public', 'active' => 'events'], function () use ($departments, $department, $days, $calendars) {
    $icon_bi = fn(array $r) => $r['kind'] === 'event' ? ($r['icon'] ?: 'bi-calendar-event') : 'bi-calendar-check';
    $time    = function (array $r): string {
        if (in_array($r['is_all_day'], [true, 1, '1', 't', 'true'], true) || !$r['time_start']) return 'ganztägig';
        return substr($r['time_start'], 0, 5) . ($r['time_end'] ? '–' . substr($r['time_end'], 0, 5) : '');
    };
    ?>
    <?php render_page_header('Termine'); ?>
    <?php render_department_filter($departments, $department, '/guest/events'); ?>

    <?php if (!$days): ?>
        <?php render_empty('calendar3', 'Keine Termine', 'Es sind derzeit keine Termine für Gäste freigegeben.'); ?>
    <?php else: foreach ($days as $date => $rows):
        render_collection_group(dashboard_day_label($date), function () use ($rows, $icon_bi, $time) { ?>
        <div class="list-group">
            <?php foreach ($rows as $r): ?>
            <div class="list-group-item d-flex align-items-start gap-3">
                <i class="bi <?= e($icon_bi($r)) ?> text-muted mt-1" aria-hidden="true"></i>
                <span class="flex-grow-1 min-w-0">
                    <span class="d-block fw-semibold text-break"><?= e($r['title']) ?></span>
                    <span class="d-flex align-items-center gap-2 flex-wrap small">
                        <span class="text-muted"><?= e($time($r)) ?></span>
                        <?php render_badge('dim', ($r['dept_icon'] ? $r['dept_icon'] . ' ' : '') . $r['team_name'], 'bi-people'); ?>
                    </span>
                    <?php render_place($r['location'], []); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    <?php }); endforeach; endif; ?>

    <?php if ($calendars): ?>
    <div class="card mt-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-calendar2-check" aria-hidden="true"></i>
            <span class="fw-semibold">Kalender abonnieren</span>
        </div>
        <div class="card-body">
            <p class="text-body-secondary small mb-3">Die Termine eines Teams in Apple Kalender, Google Calendar oder Outlook — nur Titel, Ort und Zeit.</p>
            <div class="list-group">
                <?php foreach ($calendars as $c): ?>
                <a href="<?= e(webcal_url($c['url'])) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <span class="flex-grow-1"><?= e($c['team_name']) ?> <span class="text-muted small"><?= e($c['department_name']) ?></span></span>
                    <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php
});
