<?php
// src/admin/organizations_handler.php — GET: list all organizations

declare(strict_types=1);

require_admin();
$pdo = get_db();

// Active organizations first, then inactive; alphabetical within each group
$stmt = $pdo->query("SELECT id, name, is_active, created_at FROM organizations ORDER BY is_active DESC, name ASC");
$organizations = $stmt->fetchAll();

$active_organizations   = array_filter($organizations, fn($c) => $c['is_active']);
$inactive_organizations = array_filter($organizations, fn($c) => !$c['is_active']);

$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Organisationen', 'organizations', function() use ($active_organizations, $inactive_organizations, $error) {
    require ROOT_PATH . '/src/templates/admin/organizations.php';
});
