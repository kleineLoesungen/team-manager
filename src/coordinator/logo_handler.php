<?php
// src/coordinator/logo_handler.php — GET/POST /coordinator/logo

declare(strict_types=1);

require_coordinator();

$pdo   = get_db();
$error = '';

// Fetch current team logo_path
$stmt     = $pdo->prepare("SELECT logo_path FROM teams WHERE id = ?");
$stmt->execute([(int)$_SESSION['team_id']]);
$team_row = $stmt->fetch(PDO::FETCH_ASSOC);
$current_logo = $team_row['logo_path'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (($_POST['action'] ?? '') === 'delete_logo') {
        if ($current_logo && file_exists(ROOT_PATH . '/' . ltrim($current_logo, '/'))) {
            @unlink(ROOT_PATH . '/' . ltrim($current_logo, '/'));
        }
        $pdo->prepare("UPDATE teams SET logo_path = NULL WHERE id = ?")
            ->execute([(int)$_SESSION['team_id']]);
        redirect('/coordinator/logo?deleted=1');
    }

    if (!empty($_FILES['team_logo']['tmp_name'])) {
        $file    = $_FILES['team_logo'];
        $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $error = 'Nur PNG, JPEG, GIF, WebP oder SVG-Bilder erlaubt.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = 'Bild ist zu groß (max. 2 MB).';
        } else {
            $ext        = match($mime) {
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
            // Delete old team logo file if it exists
            if ($current_logo && file_exists(ROOT_PATH . '/' . ltrim($current_logo, '/'))) {
                @unlink(ROOT_PATH . '/' . ltrim($current_logo, '/'));
            }
            $filename = 'team_' . (int)$_SESSION['team_id'] . '_logo_' . time() . '.' . $ext;
            $dest     = $upload_dir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $error = 'Hochladen fehlgeschlagen.';
            } else {
                $rel_path = 'uploads/' . $filename;
                $upd = $pdo->prepare(
                    "UPDATE teams SET logo_path = ? WHERE id = ?"
                );
                $upd->execute([$rel_path, (int)$_SESSION['team_id']]);
                redirect('/coordinator/logo?success=1');
            }
        }
    } else {
        $error = 'Bitte wähle eine Datei aus.';
    }
}

$success = !empty($_GET['success']);
$deleted = !empty($_GET['deleted']);

// Fetch admin default logo (shown as read-only when team has no own logo)
$stmt_default = $pdo->prepare("SELECT value FROM settings WHERE key = 'default_team_logo'");
$stmt_default->execute();
$default_logo = $stmt_default->fetchColumn() ?: '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Team-Logo', 'logo', function() use ($error, $success, $deleted, $current_logo, $default_logo) {
    require ROOT_PATH . '/src/templates/coordinator/logo.php';
});
