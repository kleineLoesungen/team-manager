<?php
// src/admin/settings_handler.php — GET/POST /admin/settings

declare(strict_types=1);

require_admin();

$pdo   = get_db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (($_POST['action'] ?? '') === 'delete_default_logo') {
        $old_stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'default_team_logo'");
        $old_stmt->execute();
        $old_path = $old_stmt->fetchColumn();
        if ($old_path && file_exists(ROOT_PATH . '/' . ltrim($old_path, '/'))) {
            @unlink(ROOT_PATH . '/' . ltrim($old_path, '/'));
        }
        $pdo->prepare("DELETE FROM settings WHERE key = 'default_team_logo'")->execute();
        redirect('/admin/settings?logo_deleted=1');
    }

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

        $show_coord = !empty($_POST['show_coordinators_for_members']) ? 'true' : 'false';
        $pdo->prepare(
            "INSERT INTO settings (key, value) VALUES ('show_coordinators_for_members', ?)
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value"
        )->execute([$show_coord]);

        // Handle default logo upload (optional — skip if no file submitted)
        if (!empty($_FILES['default_logo']['tmp_name'])) {
            $file    = $_FILES['default_logo'];
            $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
            $finfo   = new finfo(FILEINFO_MIME_TYPE);
            $mime    = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) {
                $error = 'Nur PNG, JPEG, GIF, WebP oder SVG-Bilder erlaubt.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Bild ist zu groß (max. 2 MB).';
            } else {
                $ext = match($mime) {
                    'image/png'     => 'png',
                    'image/jpeg'    => 'jpg',
                    'image/gif'     => 'gif',
                    'image/webp'    => 'webp',
                    'image/svg+xml' => 'svg',
                };
                $upload_dir = ROOT_PATH . '/uploads';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                // Delete old default logo file if it exists
                $old_stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'default_team_logo'");
                $old_stmt->execute();
                $old_path = $old_stmt->fetchColumn();
                if ($old_path && file_exists(ROOT_PATH . '/' . ltrim($old_path, '/'))) {
                    @unlink(ROOT_PATH . '/' . ltrim($old_path, '/'));
                }
                $filename = 'default_logo_' . time() . '.' . $ext;
                $dest     = $upload_dir . '/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $error = 'Hochladen fehlgeschlagen.';
                } else {
                    $rel_path  = 'uploads/' . $filename;
                    $stmt_logo = $pdo->prepare(
                        "INSERT INTO settings (key, value) VALUES ('default_team_logo', ?)
                         ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value"
                    );
                    $stmt_logo->execute([$rel_path]);
                }
            }
        }

        if ($error === '') {
            redirect('/admin/settings?success=1');
        }
    }
}

$stmt      = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_title'");
$stmt->execute();
$app_title = $stmt->fetchColumn() ?: 'Team Manager';

$stmt2     = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_color'");
$stmt2->execute();
$app_color = $stmt2->fetchColumn() ?: '#2563eb';

$stmt3     = $pdo->prepare("SELECT value FROM settings WHERE key = 'default_team_logo'");
$stmt3->execute();
$default_logo = $stmt3->fetchColumn() ?: '';

$stmt4    = $pdo->prepare("SELECT value FROM settings WHERE key = 'show_coordinators_for_members'");
$stmt4->execute();
$show_coordinators_for_members = $stmt4->fetchColumn() === 'true';

$success      = !empty($_GET['success']);
$logo_deleted = !empty($_GET['logo_deleted']);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Einstellungen', 'settings', function() use ($app_title, $app_color, $default_logo, $show_coordinators_for_members, $error, $success, $logo_deleted) {
    ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">Gespeichert.</div><?php endif; ?>
    <?php if ($logo_deleted ?? false): ?><div class="alert alert-success">Standard-Logo gelöscht.</div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="/admin/settings" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label for="app_title" class="form-label fw-semibold">App-Titel</label>
                    <input type="text" class="form-control" id="app_title" name="app_title"
                           value="<?= e($app_title) ?>" maxlength="100" required>
                    <div class="form-text">Wird in der Navigationsleiste für Koordinatoren und Mitglieder angezeigt.</div>
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
                <div class="mb-4">
                    <label for="default_logo" class="form-label fw-semibold">Standard-Logo (Fallback für Teams ohne eigenes Logo)</label>
                    <input type="file" class="form-control" id="default_logo" name="default_logo"
                           accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
                    <div class="form-text">Wird nur für Teams verwendet, die noch kein eigenes Logo hochgeladen haben. Max. 2 MB. PNG, JPEG, GIF, WebP, SVG.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Koordinatoren aller Teams</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox"
                               id="show_coordinators_for_members"
                               name="show_coordinators_for_members" value="1"
                               <?= $show_coordinators_for_members ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_coordinators_for_members">
                            Koordinatoren aller Teams für Mitglieder anzeigen
                        </label>
                    </div>
                    <div class="form-text">Mitglieder sehen immer die Koordinatoren ihres eigenen Teams. Wenn aktiviert, werden zusätzlich Koordinatoren aller anderen Teams angezeigt.</div>
                </div>
                <button type="submit" class="btn btn-primary min-touch">Speichern</button>
            </form>

            <?php if ($default_logo): ?>
            <div class="mt-4 pt-3" style="border-top: .5px solid var(--line)">
                <p class="fw-semibold small mb-2">Aktuelles Standard-Logo</p>
                <img src="/logo?t=<?= time() ?>" alt="Aktuelles Standard-Logo"
                     style="max-height:64px; max-width:160px; object-fit:contain;" class="d-block border rounded p-1 mb-3">
                <form method="POST" action="/admin/settings">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_default_logo">
                    <button type="submit" class="btn btn-outline-danger btn-sm min-touch"
                            onclick="return confirm('Standard-Logo wirklich löschen?')">
                        <i class="bi bi-trash me-1"></i>Standard-Logo löschen
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="list-group mt-4">
        <a href="/admin/attributes" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
            <i class="bi bi-tags fs-5"></i>
            <span class="flex-grow-1">Attribut-Gruppen</span>
            <i class="bi bi-chevron-right text-muted small"></i>
        </a>
        <a href="/admin/notify" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
            <i class="bi bi-envelope fs-5"></i>
            <span class="flex-grow-1">Benachrichtigungen</span>
            <i class="bi bi-chevron-right text-muted small"></i>
        </a>
        <a href="/admin/columns" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
            <i class="bi bi-columns-gap fs-5"></i>
            <span class="flex-grow-1">Systemspalten</span>
            <i class="bi bi-chevron-right text-muted small"></i>
        </a>
        <a href="/logout" class="list-group-item list-group-item-action d-flex align-items-center gap-3 text-danger">
            <i class="bi bi-box-arrow-right fs-5"></i>
            <span class="flex-grow-1">Abmelden</span>
        </a>
    </div>
    <?php
});
