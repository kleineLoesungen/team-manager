<?php
// src/admin/coordinator_settings_handler.php — GET /admin/coordinators/{id}/settings
// Admin-only: manage coordinator team assignments and club relation.

declare(strict_types=1);

require_admin();

$coordinator_id = (int)($_REQUEST['coordinator_id'] ?? 0);
if ($coordinator_id <= 0) {
    redirect('/admin/coordinators');
}

$pdo = get_db();

$check = $pdo->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.username, u.club_id, cl.name AS club_name
     FROM users u
     LEFT JOIN clubs cl ON cl.id = u.club_id
     WHERE u.id = ? AND u.role = 'coordinator'"
);
$check->execute([$coordinator_id]);
$coordinator = $check->fetch(PDO::FETCH_ASSOC);

if (!$coordinator) {
    redirect('/admin/coordinators');
}

$ct_stmt = $pdo->prepare(
    "SELECT ct.team_id, t.name AS team_name
     FROM coordinator_teams ct
     JOIN teams t ON t.id = ct.team_id
     WHERE ct.user_id = ? AND ct.left_at IS NULL
     ORDER BY ct.joined_at ASC"
);
$ct_stmt->execute([$coordinator_id]);
$assigned_teams = $ct_stmt->fetchAll();
$assigned_ids   = array_column($assigned_teams, 'team_id');

$all_teams       = $pdo->query("SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY name")->fetchAll();
$available_teams = array_filter($all_teams, fn($t) => !in_array($t['id'], $assigned_ids));

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? e($_GET['success']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page(
    'Einstellungen — ' . e($coordinator['first_name'] . ' ' . $coordinator['last_name']),
    'coordinators',
    function() use ($coordinator, $assigned_teams, $available_teams, $clubs, $error, $success) {
        require ROOT_PATH . '/src/templates/admin/coordinator_settings.php';
    }
);
