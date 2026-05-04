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

        $app_color_raw = trim($_POST['app_color'] ?? '');
        $app_color = preg_match('/^#[0-9a-fA-F]{6}$/', $app_color_raw) ? $app_color_raw : '#2563eb';
        $stmt2 = $pdo->prepare(
            "INSERT INTO settings (key, value) VALUES ('app_color', ?)
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value"
        );
        $stmt2->execute([$app_color]);

        redirect('/admin/settings?success=1');
    }
}

$stmt      = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_title'");
$stmt->execute();
$app_title = $stmt->fetchColumn() ?: 'Team Manager';

$stmt2     = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_color'");
$stmt2->execute();
$app_color = $stmt2->fetchColumn() ?: '#2563eb';

$success   = !empty($_GET['success']);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Einstellungen', 'settings', function() use ($app_title, $app_color, $error, $success) {
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
                <div class="mb-4">
                    <label for="app_color" class="form-label fw-semibold">Brand-Farbe</label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" class="form-control form-control-color"
                               id="app_color" name="app_color"
                               value="<?= e($app_color) ?>" style="width:48px; height:38px; padding:2px;">
                        <input type="text" class="form-control" style="max-width:120px;"
                               id="app_color_text" value="<?= e($app_color) ?>"
                               pattern="^#[0-9a-fA-F]{6}$" maxlength="7" placeholder="#2563eb"
                               oninput="document.getElementById('app_color').value=this.value">
                        <script>
                        document.getElementById('app_color').addEventListener('input', function() {
                            document.getElementById('app_color_text').value = this.value;
                        });
                        </script>
                    </div>
                    <div class="form-text">Hex-Farbe für Navigationsleiste und Buttons (z.B. #2563eb).</div>
                </div>
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
            </form>
        </div>
    </div>
    <?php
});
