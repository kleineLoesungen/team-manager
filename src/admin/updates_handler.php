<?php
// src/admin/updates_handler.php — GET /admin/updates: Version und Änderungen; POST: jetzt prüfen
// Siehe src/utils/updates.php (Issue #8).

declare(strict_types=1);

require_admin();
require_once ROOT_PATH . '/src/utils/updates.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $status = update_status($pdo, true);
    redirect('/admin/updates?' . ($status['ok'] ? 'checked=1' : 'failed=1'));
}

$status = update_status($pdo);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Version', 'settings', function () use ($status) {
    require ROOT_PATH . '/src/templates/admin/updates.php';
});
