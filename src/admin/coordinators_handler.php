<?php
// src/admin/coordinators_handler.php — Admin coordinators list page

declare(strict_types=1);

require_admin();

$pdo = get_db();

$coordinators_stmt = $pdo->query(
    "SELECT u.id, u.team_id, u.first_name, u.last_name, u.username, u.is_active,
            t.name AS team_name
     FROM users u
     LEFT JOIN teams t ON t.id = u.team_id
     WHERE u.role = 'coordinator'
     ORDER BY u.first_name, u.last_name"
);
$coordinators = $coordinators_stmt->fetchAll();

$teams_stmt = $pdo->query("SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY name");
$teams = $teams_stmt->fetchAll();

$error = !empty($_GET['error']) ? e($_GET['error']) : '';

$active_coordinators   = array_filter($coordinators, fn($c) => $c['is_active']);
$inactive_coordinators = array_filter($coordinators, fn($c) => !$c['is_active']);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Koordinatoren verwalten', 'coordinators', function() use ($active_coordinators, $inactive_coordinators, $teams, $error) {
    if ($error) {
        echo '<div class="alert alert-danger">' . $error . '</div>';
    }
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <span class="text-muted"><?= count($active_coordinators) ?> aktive Koordinatoren</span>
        <a href="/admin/coordinators/create" class="btn btn-primary min-touch">
            <i class="bi bi-plus-lg me-1"></i>Koordinator hinzufügen
        </a>
    </div>

    <?php if (empty($active_coordinators) && empty($inactive_coordinators)): ?>
    <div class="text-center py-5">
        <p class="h5 text-muted">Keine Koordinatoren zugewiesen</p>
        <p class="text-muted">Füge einen oder mehrere Koordinatoren hinzu.</p>
    </div>
    <?php else: ?>

    <?php if (!empty($active_coordinators)): ?>
    <div class="list-group">
        <?php foreach ($active_coordinators as $coordinator): ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= e($coordinator['first_name'] . ' ' . $coordinator['last_name']) ?></strong>
                    <code class="ms-2 text-muted small"><?= e($coordinator['username']) ?></code>
                    <div class="text-muted small"><?= e($coordinator['team_name'] ?? '—') ?></div>
                </div>
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <form method="POST"
                          action="/admin/coordinators/<?= $coordinator['id'] ?>/deactivate"
                          onsubmit="return confirm('<?= e('Der Koordinator wird deaktiviert und kann sich nicht mehr anmelden.') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            Deaktivieren
                        </button>
                    </form>
                    <form method="POST"
                          action="/admin/coordinators/<?= $coordinator['id'] ?>/reset-password"
                          onsubmit="return confirm('<?= e('Das Passwort wird zurückgesetzt und angezeigt. Diese Aktion kann nicht rückgängig gemacht werden.') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            Passwort zurücksetzen
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
                            <div class="text-muted small"><?= e($coordinator['team_name'] ?? '—') ?></div>
                        </div>
                        <form method="POST"
                              action="/admin/coordinators/<?= $coordinator['id'] ?>/reactivate"
                              onsubmit="return confirm('<?= e('Der Koordinator wird reaktiviert und kann sich wieder anmelden.') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                            </button>
                        </form>
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
