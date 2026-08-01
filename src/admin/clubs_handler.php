<?php
// src/admin/clubs_handler.php — GET: list all clubs

declare(strict_types=1);

require_admin();
$pdo = get_db();

// Active clubs first, then inactive; alphabetical within each group
$stmt = $pdo->query("SELECT id, name, is_active, created_at FROM clubs ORDER BY is_active DESC, name ASC");
$clubs = $stmt->fetchAll();

$active_clubs   = array_filter($clubs, fn($c) => $c['is_active']);
$inactive_clubs = array_filter($clubs, fn($c) => !$c['is_active']);

$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Klubs', 'clubs', function() use ($active_clubs, $inactive_clubs, $error) {
    require ROOT_PATH . '/src/templates/admin/clubs.php';
});
