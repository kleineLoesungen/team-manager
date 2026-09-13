<?php
// src/templates/admin/dashboard.php — Admin teams dashboard
// Variables: $teams (array of team rows), $coaches_by_team (array keyed by team_id)

$active_teams   = array_filter($teams, fn($t) => $t['is_active']);
$inactive_teams = array_filter($teams, fn($t) => !$t['is_active']);
?>
<?php if (!empty($_GET['error'])): render_flash('error', $_GET['error']); endif; ?>
<?php if (!empty($_GET['success'])): render_flash('success', $_GET['success']); endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_teams) ?> aktive(s) Team(s)</span>
    <a href="/admin/teams/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Team erstellen
    </a>
</div>

<?php if (empty($teams)): ?>
<?php render_empty('people-fill', 'Noch keine Teams', 'Erstelle das erste Team.'); ?>
<?php else: ?>

<?php if (!empty($active_teams)): ?>
<?php render_collection_group('Aktive Teams', function() use ($active_teams, $coaches_by_team): void { ?>
<div class="list-group mb-3">
    <?php foreach ($active_teams as $team): ?>
    <div class="list-group-item px-3 py-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-semibold">
                <?php if ($team['sort_order'] !== 0): ?>
                <span class="text-muted fw-normal me-1 small"><?= (int)$team['sort_order'] ?>.</span>
                <?php endif; ?>
                <?= e($team['name']) ?>
                <?php render_badge('ok', 'Aktiv'); ?>
            </div>
        </div>
        <?php
        $coaches = $coaches_by_team[$team['id']] ?? [];
        $count   = count($coaches);
        ?>
        <div class="text-muted small">
            <?= $count === 0 ? 'Keine Koordinatoren zugewiesen' : $count . ' Koordinator' . ($count === 1 ? '' : 'en') . ' zugewiesen' ?>
        </div>
        <?php if (!empty($coaches)): ?>
        <div class="text-muted small mt-2">
            <?= implode(', ', array_map(fn($c) => e($c['first_name'] . ' ' . $c['last_name']), $coaches)) ?>
        </div>
        <?php endif; ?>
        <div class="d-flex gap-2 flex-wrap mt-2">
            <a href="/admin/teams/<?= (int)$team['id'] ?>/edit" data-save-scroll
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Bearbeiten
            </a>
            <form method="POST" action="/admin/teams/<?= (int)$team['id'] ?>/deactivate"
                  onsubmit="return confirm('<?= e('Das Team wird deaktiviert. Alle Koordinatoren und Mitglieder bleiben im System, können sich aber nicht anmelden.') ?>')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pause-circle me-1"></i>Team deaktivieren
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php }); ?>
<?php endif; ?>

<?php if (!empty($inactive_teams)): ?>
<?php render_collection_group('Inaktive Teams', function() use ($inactive_teams, $coaches_by_team): void { ?>
<div class="list-group opacity-75 mb-3">
    <?php foreach ($inactive_teams as $team): ?>
    <div class="list-group-item px-3 py-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-semibold text-muted">
                <?= e($team['name']) ?>
                <?php render_badge('dim', 'Inaktiv'); ?>
            </div>
        </div>
        <?php
        $count = count($coaches_by_team[$team['id']] ?? []);
        ?>
        <div class="text-muted small">
            <?= $count === 0 ? 'Keine Koordinatoren zugewiesen' : $count . ' Koordinator' . ($count === 1 ? '' : 'en') . ' zugewiesen' ?>
        </div>
        <div class="d-flex gap-2 flex-wrap mt-2">
            <form method="POST" action="/admin/teams/<?= (int)$team['id'] ?>/reactivate"
                  onsubmit="return confirm('<?= e('Das Team wird reaktiviert. Koordinatoren können sich wieder anmelden.') ?>')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                </button>
            </form>
            <form method="POST" action="/admin/teams/<?= (int)$team['id'] ?>/delete"
                  onsubmit="return confirm('<?= e('Team „' . $team['name'] . '" endgültig löschen? Alle Mitglieder, Listen, Ticker und sonstige Daten werden unwiderruflich gelöscht.') ?>')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="bi bi-trash me-1"></i>Endgültig löschen
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php }); ?>
<?php endif; ?>

<?php endif; ?>
