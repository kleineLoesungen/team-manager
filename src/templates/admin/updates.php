<?php
// src/templates/admin/updates.php — Admin: installierte Version, neuere Versionen, Änderungen der installierten und früherer Versionen
// Variables: $status (update_status()), $installed_entries (installed_changelog())
$checked = $status['checked_at'] ? (new DateTimeImmutable('@' . $status['checked_at']))
    ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y, H:i') : null;

// Einträge einer Version als Gruppe: Änderungen, dann Migrationen
$render_entry = function (string $title, array $entry): void {
    render_collection_group($title, function () use ($entry) { ?>
        <div class="list-group mb-3">
            <?php foreach ($entry['items'] as $item): ?>
            <div class="list-group-item small"><?php render_changelog_item($item); ?></div>
            <?php endforeach; ?>
            <?php foreach ($entry['migrations'] as $m): ?>
            <div class="list-group-item small"><?php render_badge('warn', 'Migration', 'bi-database'); ?> <?= e($m) ?></div>
            <?php endforeach; ?>
        </div>
    <?php });
};
?>
<?php if (!empty($_GET['checked'])): render_flash('success', 'Geprüft.'); endif; ?>
<?php if (!empty($_GET['failed'])): render_flash('error', 'Die Prüfung war nicht möglich. Versuch es später erneut.'); endif; ?>

<?php render_page_header('Version', '/admin/settings'); ?>

<?php render_migration_steps($status['db_pending'], 'Datenbank nicht aktuell — jetzt einspielen', 'danger'); ?>

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
    <?php
    // Migrationen der neueren Versionen, älteste zuerst (Changelog ist neueste zuerst)
    $migrations = array_merge(...array_map(fn($e) => $e['migrations'], array_reverse($status['newer'])));
    sort($migrations, SORT_STRING);
    render_migration_steps(array_values(array_unique($migrations)),
        'Vor dem Deployment Migration' . (count($migrations) === 1 ? '' : 'en') . ' einspielen');
    ?>
    <?php foreach ($status['newer'] as $entry) $render_entry('Version ' . version_label($entry['version']), $entry); ?>
<?php endif; ?>

<?php if ($status['enabled']): ?>
<form method="POST" action="/admin/updates" class="d-grid mt-4">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-secondary min-touch">
        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Jetzt erneut prüfen
    </button>
</form>
<?php endif; ?>

<?php if ($installed_entries): ?>
    <h2 class="h6 fw-semibold text-muted mt-4 mb-2">In dieser Version</h2>
    <?php $render_entry('Version ' . version_label($installed_entries[0]['version']), $installed_entries[0]); ?>
    <?php if (count($installed_entries) > 1): ?>
    <h2 class="h6 fw-semibold text-muted mt-4 mb-2">Frühere Versionen</h2>
    <?php foreach (array_slice($installed_entries, 1) as $entry) $render_entry('Version ' . version_label($entry['version']), $entry); ?>
    <?php endif; ?>
<?php endif; ?>
