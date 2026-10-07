<?php
// src/templates/admin/updates.php — Admin: installierte Version, neuere Versionen mit Änderungen
// Variables: $status (update_status())
$checked = $status['checked_at'] ? (new DateTimeImmutable('@' . $status['checked_at']))
    ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y, H:i') : null;
?>
<?php if (!empty($_GET['checked'])): render_flash('success', 'Geprüft.'); endif; ?>
<?php if (!empty($_GET['failed'])): render_flash('error', 'Die Prüfung war nicht möglich. Versuch es später erneut.'); endif; ?>

<?php render_page_header('Version', '/admin/settings'); ?>

<div class="list-group mb-4">
    <div class="list-group-item d-flex align-items-center gap-2">
        <span class="flex-grow-1">Installiert</span>
        <span class="fw-semibold"><?= $status['installed'] ? e(version_label($status['installed'])) : 'unbekannt' ?></span>
    </div>
    <?php if ($status['enabled']): ?>
    <div class="list-group-item d-flex align-items-center gap-2">
        <span class="flex-grow-1">Neueste</span>
        <span class="fw-semibold"><?= $status['latest'] ? e(version_label($status['latest'])) : '–' ?></span>
    </div>
    <div class="list-group-item d-flex align-items-center gap-2">
        <span class="flex-grow-1">Zuletzt geprüft</span>
        <span class="small text-muted"><?= $checked ? e($checked) : 'noch nie' ?><?= !$status['ok'] && $checked ? ' · nicht erreichbar' : '' ?></span>
    </div>
    <?php endif; ?>
</div>

<?php if (!$status['enabled']): ?>
    <p class="text-muted small">Die Prüfung auf Updates ist abgeschaltet (<code>UPDATE_CHECK_URL</code> in der config.php ist leer).</p>
<?php elseif ($status['installed'] === null): ?>
    <?php render_flash('error', 'Die installierte Version ist unbekannt: CHANGELOG.md fehlt auf dem Server oder hat keinen Eintrag. Lade die Datei beim nächsten Deployment mit hoch.'); ?>
<?php elseif (!$status['newer']): ?>
    <?php render_empty('check-circle', 'Aktuell', 'Diese Instanz hat die neueste bekannte Version.'); ?>
<?php else: ?>
    <?php $migrations = array_merge(...array_map(fn($e) => $e['migrations'], $status['newer'])); ?>
    <?php if ($migrations): ?>
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1"><i class="bi bi-database-exclamation me-1" aria-hidden="true"></i>Vor dem Deployment Migration<?= count($migrations) === 1 ? '' : 'en' ?> einspielen</div>
        <ul class="mb-0 ps-3 small"><?php foreach ($migrations as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <?php foreach ($status['newer'] as $entry):
        render_collection_group('Version ' . version_label($entry['version']), function () use ($entry) { ?>
        <div class="list-group mb-3">
            <?php foreach ($entry['items'] as $item): ?>
            <div class="list-group-item small"><?= e($item) ?></div>
            <?php endforeach; ?>
            <?php foreach ($entry['migrations'] as $m): ?>
            <div class="list-group-item small"><?php render_badge('warn', 'Migration', 'bi-database'); ?> <?= e($m) ?></div>
            <?php endforeach; ?>
        </div>
    <?php });
    endforeach; ?>
<?php endif; ?>

<?php if ($status['enabled']): ?>
<form method="POST" action="/admin/updates" class="d-grid mt-4">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-secondary min-touch">
        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Jetzt erneut prüfen
    </button>
</form>
<?php endif; ?>
