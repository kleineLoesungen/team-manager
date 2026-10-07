<?php
// src/admin/team_create_handler.php — GET: show create form; POST: create a new team

declare(strict_types=1);

require_admin();
require_once ROOT_PATH . '/src/db/departments.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $team_name     = trim($_POST['team_name']  ?? '');
    $sort_order    = (int)($_POST['sort_order'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $valid_departments = array_map('intval', array_column(departments_list(get_db(), true), 'id'));

    if (empty($team_name) || strlen($team_name) > 100) {
        redirect('/admin/teams/create?error=' . urlencode('Teamname ist erforderlich (max. 100 Zeichen).'));
    }
    if (!in_array($department_id, $valid_departments, true)) {
        redirect('/admin/teams/create?error=' . urlencode('Wähle die Abteilung des Teams.'));
    }

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare(
            "INSERT INTO teams (name, department_id, sort_order, calendar_token_coordinator, calendar_token_member) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$team_name, $department_id, $sort_order, generate_calendar_token(), generate_calendar_token()]);
        redirect('/admin/teams?success=' . urlencode($team_name . ' erstellt.'));
    } catch (PDOException $e) {
        error_log('Team create error: ' . $e->getMessage());
        redirect('/admin/teams/create?error=' . urlencode('Ein Fehler ist aufgetreten. Bitte versuch es später erneut.'));
    }
}

// GET: render create form
$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

$departments = departments_list(get_db());

render_admin_page('Team erstellen', 'teams', function() use ($error, $departments) {
    require ROOT_PATH . '/src/templates/admin/team_create.php';
});
