<?php
// src/admin/settings_handler.php — GET/POST /admin/settings

declare(strict_types=1);

require_admin();

$pdo   = get_db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $app_title = trim($_POST['app_title'] ?? '');
    if ($app_title === '') {
        $error = 'Der App-Titel darf nicht leer sein.';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO settings (key, value) VALUES ('app_title', ?)
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value"
        );
        $stmt->execute([$app_title]);
        redirect('/admin/settings?success=1');
    }
}

$stmt      = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_title'");
$stmt->execute();
$app_title = $stmt->fetchColumn() ?: 'Team Manager';
$success   = !empty($_GET['success']);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Einstellungen', 'settings', function() use ($app_title, $error, $success) {
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">Gespeichert.</div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="/admin/settings">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label for="app_title" class="form-label fw-semibold">App-Titel</label>
                    <input type="text" class="form-control" id="app_title" name="app_title"
                           value="<?= e($app_title) ?>" maxlength="100" required>
                    <div class="form-text">Wird in der Navigationsleiste für Trainer und Spieler angezeigt.</div>
                </div>
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
            </form>
        </div>
    </div>
    <?php
});
