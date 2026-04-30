<?php
// src/admin/coaches_handler.php — Admin coaches list page

declare(strict_types=1);

require_admin();

$pdo = get_db();

$coaches_stmt = $pdo->query(
    "SELECT u.id, u.team_id, u.first_name, u.last_name, u.username, u.is_active,
            t.name AS team_name
     FROM users u
     LEFT JOIN teams t ON t.id = u.team_id
     WHERE u.role = 'coach'
     ORDER BY u.last_name, u.first_name"
);
$coaches = $coaches_stmt->fetchAll();

$teams_stmt = $pdo->query("SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY name");
$teams = $teams_stmt->fetchAll();

$error = !empty($_GET['error']) ? e($_GET['error']) : '';

$active_coaches   = array_filter($coaches, fn($c) => $c['is_active']);
$inactive_coaches = array_filter($coaches, fn($c) => !$c['is_active']);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Trainer verwalten', 'coaches', function() use ($active_coaches, $inactive_coaches, $teams, $error) {
    if ($error) {
        echo '<div class="alert alert-danger">' . $error . '</div>';
    }
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <span class="text-muted"><?= count($active_coaches) ?> aktive Trainer</span>
        <a href="/admin/coaches/create" class="btn btn-primary min-touch">
            <i class="bi bi-plus-lg me-1"></i>Trainer hinzufügen
        </a>
    </div>

    <?php if (empty($active_coaches) && empty($inactive_coaches)): ?>
    <div class="text-center py-5">
        <p class="h5 text-muted">Keine Trainer zugewiesen</p>
        <p class="text-muted">Fügen Sie einen oder mehrere Trainer hinzu.</p>
    </div>
    <?php else: ?>

    <?php if (!empty($active_coaches)): ?>
    <div class="list-group">
        <?php foreach ($active_coaches as $coach): ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= e($coach['first_name'] . ' ' . $coach['last_name']) ?></strong>
                    <code class="ms-2 text-muted small"><?= e($coach['username']) ?></code>
                    <div class="text-muted small"><?= e($coach['team_name'] ?? '—') ?></div>
                </div>
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <form method="POST"
                          action="/admin/coaches/<?= $coach['id'] ?>/deactivate"
                          onsubmit="return confirm('<?= e('Der Trainer wird deaktiviert und kann sich nicht mehr anmelden.') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            Deaktivieren
                        </button>
                    </form>
                    <form method="POST"
                          action="/admin/coaches/<?= $coach['id'] ?>/reset-password"
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

    <?php if (!empty($inactive_coaches)): ?>
    <div class="mt-4">
        <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#inactiveCoaches"
                aria-expanded="false">
            <i class="bi bi-chevron-down"></i>
            Inaktiv (<?= count($inactive_coaches) ?>)
        </button>
        <div class="collapse mt-2" id="inactiveCoaches">
            <div class="list-group opacity-75">
                <?php foreach ($inactive_coaches as $coach): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-muted"><?= e($coach['first_name'] . ' ' . $coach['last_name']) ?></strong>
                            <code class="ms-2 text-muted small"><?= e($coach['username']) ?></code>
                            <span class="badge bg-secondary ms-1">Deaktiviert</span>
                            <div class="text-muted small"><?= e($coach['team_name'] ?? '—') ?></div>
                        </div>
                        <form method="POST"
                              action="/admin/coaches/<?= $coach['id'] ?>/reactivate"
                              onsubmit="return confirm('<?= e('Der Trainer wird reaktiviert und kann sich wieder anmelden.') ?>')">
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
