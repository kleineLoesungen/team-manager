<?php
// src/admin/teams_handler.php — Admin dashboard: list all teams with coaches

declare(strict_types=1);

require_admin(); // Per D-08: every admin page checks is_admin

$pdo = get_db();

// Fetch all teams
$teams_stmt = $pdo->query("SELECT id, name, is_active, created_at FROM teams ORDER BY created_at DESC");
$teams = $teams_stmt->fetchAll();

// Fetch all coaches grouped by team_id
$coaches_stmt = $pdo->query(
    "SELECT id, team_id, first_name, last_name, username, is_active
     FROM users WHERE role = 'coach' ORDER BY last_name, first_name"
);
$coaches_by_team = [];
foreach ($coaches_stmt->fetchAll() as $coach) {
    $coaches_by_team[$coach['team_id']][] = $coach;
}

$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Teams verwalten', 'teams', function() use ($teams, $coaches_by_team, $error) {
    if ($error) {
        echo '<div class="alert alert-danger">' . $error . '</div>';
    }
    require ROOT_PATH . '/src/templates/admin/dashboard.php';
});
