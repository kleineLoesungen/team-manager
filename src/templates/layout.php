<?php
// src/templates/layout.php — Shared HTML layout functions
// All templates call render_layout_head() / render_layout_foot()
// or use the full-page wrapper render_login_page().

declare(strict_types=1);

/**
 * Output the HTML <head> section with Bootstrap 5 CDN.
 * @param string $title Page title (German, appended to "Team Manager")
 */
function render_layout_head(string $title = 'Team Manager'): void {
    $full_title = $title !== 'Team Manager' ? e($title) . ' — Team Manager' : 'Team Manager';
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $full_title ?></title>
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
          crossorigin="anonymous">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          rel="stylesheet">
    <style>
        /* Mobile-first base styles */
        body { font-size: 1rem; line-height: 1.5; }
        .min-touch { min-height: 44px; }
        code, .credential-block { font-family: 'SFMono-Regular', Consolas, monospace; }
        .credential-block {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <?php
}

/**
 * Output the shared navigation bar.
 * Shows app name left, current user + logout right.
 */
function render_navbar(): void {
    static $app_title = null;
    if ($app_title === null) {
        try {
            $pdo   = get_db();
            $stmt  = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_title'");
            $stmt->execute();
            $app_title = $stmt->fetchColumn() ?: 'Team Manager';
        } catch (Throwable $e) {
            $app_title = 'Team Manager';
        }
    }

    $brand = e($app_title);
    if (!empty($_SESSION['team_name'])) {
        $brand .= ' · ' . e($_SESSION['team_name']);
    }

    $display_name = '';
    $role_label   = '';

    if (!empty($_SESSION['is_admin'])) {
        $display_name = e(ADMIN_USERNAME);
        $role_label   = 'Admin';
    } elseif (!empty($_SESSION['display_name'])) {
        $display_name = e($_SESSION['display_name']);
        $role_label   = ($_SESSION['role'] ?? '') === 'coach' ? 'Trainer' : 'Spieler';
    }
    ?>
    <nav class="navbar navbar-light bg-white border-bottom px-3">
        <span class="navbar-brand fw-semibold"><?= $brand ?></span>
        <?php if ($display_name): ?>
        <div class="d-flex align-items-center gap-3">
            <small class="text-muted">
                <?= $display_name ?> <span class="badge bg-secondary"><?= e($role_label) ?></span>
            </small>
            <a href="/logout" class="btn btn-sm btn-outline-secondary">Abmelden</a>
        </div>
        <?php endif; ?>
    </nav>
    <?php
}

/**
 * Output closing Bootstrap scripts and </body></html>.
 */
function render_layout_foot(): void {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
            crossorigin="anonymous"></script>
</body>
</html>
    <?php
}

/**
 * Render the login page (called from login_handler.php).
 * @param string $error German error message to display, or empty string
 * @param string $message German info message (e.g., session expired), or empty string
 */
function render_login_page(string $error = '', string $message = ''): void {
    render_layout_head('Anmelden');
    require ROOT_PATH . '/src/templates/login.php';
    render_layout_foot();
    exit;
}
