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

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Trainer verwalten', 'coaches', function() use ($coaches, $teams, $error) {
    if ($error) {
        echo '<div class="alert alert-danger">' . $error . '</div>';
    }
    // Inline coaches table + create button
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <span class="text-muted"><?= count($coaches) ?> Trainer</span>
        <a href="/admin/coaches/create" class="btn btn-primary min-touch">
            <i class="bi bi-plus-lg me-1"></i>Trainer hinzufügen
        </a>
    </div>
    <?php if (empty($coaches)): ?>
    <div class="text-center py-5">
        <p class="h5 text-muted">Keine Trainer zugewiesen</p>
        <p class="text-muted">Fügen Sie einen oder mehrere Trainer hinzu.</p>
    </div>
    <?php else: ?>
    <div class="list-group">
        <?php foreach ($coaches as $coach): ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= e($coach['first_name'] . ' ' . $coach['last_name']) ?></strong>
                    <code class="ms-2 text-muted small"><?= e($coach['username']) ?></code>
                    <?php if (!$coach['is_active']): ?>
                    <span class="badge bg-secondary ms-1">Deaktiviert</span>
                    <?php endif; ?>
                    <div class="text-muted small"><?= e($coach['team_name'] ?? '—') ?></div>
                </div>
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
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php
});
