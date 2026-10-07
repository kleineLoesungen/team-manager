<?php
// src/templates/member/event.php — Termin ansehen (Mitglieder)
// Variables: $event (event_load()), $can_edit (bool), $resource_names (string[])
$icon  = preg_match('/^bi-[a-z0-9-]+$/', (string)$event['icon']) ? $event['icon'] : 'bi-calendar-event';
$start = $event['time_start'] ? substr((string)$event['time_start'], 0, 5) : null;
$end   = $event['time_end'] ? substr((string)$event['time_end'], 0, 5) : null;
?>
<?php if (!empty($_GET['success'])): render_flash('success', 'Gespeichert.'); endif; ?>
<?php if (!empty($_GET['conflicts'])) render_resource_conflict_notice((int)$_GET['conflicts'], '/member/resources'); ?>

<div class="mb-3">
    <a class="back-to-contents btn btn-sm btn-outline-secondary" href="/member/contents">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Zurück zur Übersicht
    </a>
</div>

<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
    <i class="bi <?= e($icon) ?> text-muted" aria-hidden="true"></i>
    <span class="text-muted small">
        <?= e(date('d.m.Y', strtotime($event['date']))) ?>
        · <?= $start ? e($start . ($end ? '–' . $end : '')) : 'ganztägig' ?>
    </span>
</div>
<?php render_place($event['location'] ?? null, $resource_names); ?>

<?php if (!empty($event['description'])): ?>
<p class="mb-3"><?= nl2br(e($event['description'])) ?></p>
<?php endif; ?>

<?php if (!empty($event['creator_name'])): ?>
<p class="small text-muted mb-3"><i class="bi bi-person me-1" aria-hidden="true"></i>Angelegt von <?= e($event['creator_name']) ?></p>
<?php endif; ?>

<?php if ($can_edit): ?>
<a href="/member/events/<?= (int)$event['id'] ?>/edit" class="btn btn-primary min-touch">
    <i class="bi bi-pencil me-1" aria-hidden="true"></i>Termin bearbeiten
</a>
<?php endif; ?>

<script>(function(){var s=sessionStorage.getItem('member_contents_url');if(s)document.querySelectorAll('.back-to-contents').forEach(function(a){a.href=s;});})();</script>
