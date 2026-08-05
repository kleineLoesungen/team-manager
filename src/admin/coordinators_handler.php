<?php
// src/admin/coordinators_handler.php — Admin coordinators list page

declare(strict_types=1);

require_admin();

$pdo = get_db();

$coordinators_stmt = $pdo->query(
    "SELECT u.id, u.first_name, u.last_name, u.username, u.is_active, u.email, u.phone,
            cl.name AS club_name
     FROM users u
     LEFT JOIN clubs cl ON cl.id = u.club_id
     WHERE u.role = 'coordinator'
     ORDER BY u.first_name, u.last_name"
);
$coordinators = $coordinators_stmt->fetchAll();

// Read-only team badges per coordinator
$ct_stmt = $pdo->query(
    "SELECT ct.user_id, t.name AS team_name
     FROM coordinator_teams ct
     JOIN teams t ON t.id = ct.team_id
     WHERE ct.left_at IS NULL AND t.is_active = TRUE
     ORDER BY ct.joined_at ASC"
);
$coordinator_teams_map = [];
foreach ($ct_stmt->fetchAll() as $row) {
    $coordinator_teams_map[$row['user_id']][] = $row['team_name'];
}

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? e($_GET['success']) : '';

$active_coordinators   = array_filter($coordinators, fn($c) => $c['is_active']);
$inactive_coordinators = array_filter($coordinators, fn($c) => !$c['is_active']);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Koordinatoren verwalten', 'coordinators', function() use ($active_coordinators, $inactive_coordinators, $coordinator_teams_map, $error, $success) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <span class="text-muted"><?= count($active_coordinators) ?> aktive Koordinatoren</span>
        <a href="/admin/coordinators/create" class="btn btn-primary min-touch">
            <i class="bi bi-plus-lg me-1"></i>Koordinator hinzufügen
        </a>
    </div>

    <?php if (empty($active_coordinators) && empty($inactive_coordinators)): ?>
    <div class="text-center py-5">
        <p class="h5 text-muted">Keine Koordinatoren vorhanden</p>
        <p class="text-muted">Füge einen oder mehrere Koordinatoren hinzu.</p>
    </div>
    <?php else: ?>

    <?php if (!empty($active_coordinators)): ?>
    <div class="list-group">
        <?php foreach ($active_coordinators as $coordinator): ?>
        <?php $my_teams = $coordinator_teams_map[$coordinator['id']] ?? []; ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1 min-w-0">
                    <div class="mb-1">
                        <strong><?= e($coordinator['first_name'] . ' ' . $coordinator['last_name']) ?></strong>
                        <code class="ms-2 text-muted small"><?= e($coordinator['username']) ?></code>
                    </div>
                    <!-- Club badge -->
                    <?php if (!empty($coordinator['club_name'])): ?>
                    <div class="mb-1">
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">
                            <i class="bi bi-building me-1"></i><?= e($coordinator['club_name']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <!-- Team badges (read-only) -->
                    <?php if (!empty($my_teams)): ?>
                    <div class="d-flex flex-wrap gap-1 mb-1">
                        <?php foreach ($my_teams as $team_name): ?>
                        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                            <?= e($team_name) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="mb-1"><span class="text-muted small">Kein Team zugewiesen</span></div>
                    <?php endif; ?>
                    <!-- Contact info -->
                    <div class="text-muted small">
                        <?php if (!empty($coordinator['email'])): ?>
                            <i class="bi bi-envelope me-1"></i><?= e($coordinator['email']) ?>
                        <?php else: ?>
                            <span class="text-warning small"><i class="bi bi-envelope-x me-1"></i>Keine E-Mail</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($coordinator['phone'])): ?>
                    <div class="text-muted small">
                        <i class="bi bi-telephone me-1"></i><?= e($coordinator['phone']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <a href="/admin/coordinators/<?= $coordinator['id'] ?>/edit-email"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-envelope me-1"></i>Kontakt
                    </a>
                    <a href="/admin/coordinators/<?= $coordinator['id'] ?>/settings"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-gear me-1"></i>Einstellungen
                    </a>
                    <form method="POST"
                          action="/admin/coordinators/<?= $coordinator['id'] ?>/reset-password"
                          onsubmit="return confirm('<?= e('Das Passwort wird zurückgesetzt und angezeigt. Diese Aktion kann nicht rückgängig gemacht werden.') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            Passwort zurücksetzen
                        </button>
                    </form>
                    <form method="POST"
                          action="/admin/coordinators/<?= $coordinator['id'] ?>/deactivate"
                          onsubmit="return confirm('<?= e('Der Koordinator wird deaktiviert und kann sich nicht mehr anmelden.') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            Deaktivieren
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($inactive_coordinators)): ?>
    <div class="mt-4">
        <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#inactiveCoordinators"
                aria-expanded="false">
            <i class="bi bi-chevron-down"></i>
            Inaktiv (<?= count($inactive_coordinators) ?>)
        </button>
        <div class="collapse mt-2" id="inactiveCoordinators">
            <div class="list-group opacity-75">
                <?php foreach ($inactive_coordinators as $coordinator): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-muted"><?= e($coordinator['first_name'] . ' ' . $coordinator['last_name']) ?></strong>
                            <code class="ms-2 text-muted small"><?= e($coordinator['username']) ?></code>
                            <span class="badge bg-secondary ms-1">Deaktiviert</span>
                            <?php if (!empty($coordinator['club_name'])): ?>
                            <div class="text-muted small mt-1">
                                <i class="bi bi-building me-1"></i><?= e($coordinator['club_name']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                        <form method="POST"
                              action="/admin/coordinators/<?= $coordinator['id'] ?>/reactivate"
                              onsubmit="return confirm('<?= e('Der Koordinator wird reaktiviert und kann sich wieder anmelden.') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                            </button>
                        </form>
                        <form method="POST"
                              action="/admin/coordinators/<?= $coordinator['id'] ?>/delete"
                              onsubmit="return confirm('<?= e('Koordinator ' . $coordinator['first_name'] . ' ' . $coordinator['last_name'] . ' endgültig löschen? Diese Aktion kann nicht rückgängig gemacht werden.') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash me-1"></i>Löschen
                            </button>
                        </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
    <?php
});
