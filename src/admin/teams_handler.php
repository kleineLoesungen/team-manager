<?php
// src/admin/teams_handler.php — Admin dashboard: list all teams with coaches

declare(strict_types=1);

require_admin(); // Per D-08: every admin page checks is_admin
require_once ROOT_PATH . '/src/db/departments.php';

$pdo = get_db();

// Abteilungen als Filter (?department=)
$departments = departments_list($pdo);
$department  = department_filter($departments);

// Fetch all teams (of the selected department)
$teams_stmt = $pdo->prepare(
    "SELECT t.id, t.name, t.is_active, t.sort_order, t.created_at, d.name AS department_name
     FROM teams t JOIN departments d ON d.id = t.department_id
     WHERE (CAST(? AS int) IS NULL OR t.department_id = ?)
     ORDER BY t.sort_order ASC, t.name ASC"
);
$teams_stmt->execute([$department, $department]);
$teams = $teams_stmt->fetchAll();

// Fetch coordinators per team via coordinator_teams (supports multi-team assignments)
$coaches_stmt = $pdo->query(
    "SELECT u.id, ct.team_id, p.first_name, p.last_name, u.username, u.is_active
     FROM coordinator_teams ct
     JOIN users u ON u.id = ct.user_id
     JOIN members p ON p.id = u.member_id
     WHERE ct.left_at IS NULL
     ORDER BY p.last_name, p.first_name"
);
$coaches_by_team = [];
foreach ($coaches_stmt->fetchAll() as $coach) {
    $coaches_by_team[$coach['team_id']][] = $coach;
}

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Teams verwalten', 'teams', function() use ($teams, $coaches_by_team, $departments, $department) {
    render_department_filter($departments, $department, '/admin/teams');
    require ROOT_PATH . '/src/templates/admin/dashboard.php';
});
