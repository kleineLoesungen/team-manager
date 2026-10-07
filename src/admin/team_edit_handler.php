<?php
// src/admin/team_edit_handler.php — GET: show edit form; POST: update team
// $_REQUEST['team_id'] set by router

declare(strict_types=1);

require_admin();
require_once ROOT_PATH . '/src/db/departments.php';

$team_id = (int)($_REQUEST['team_id'] ?? 0);
if ($team_id <= 0) {
    redirect('/admin/teams');
}

$pdo  = get_db();
$stmt = $pdo->prepare("SELECT id, name, sort_order, department_id FROM teams WHERE id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    redirect('/admin/teams');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $team_name     = trim($_POST['team_name']  ?? '');
    $sort_order    = (int)($_POST['sort_order'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    // aktive Abteilungen oder die bisherige (auch wenn sie inzwischen deaktiviert ist)
    $valid_departments = array_map('intval', array_column(departments_list($pdo, true), 'id'));
    $valid_departments[] = (int)$team['department_id'];

    if (empty($team_name) || strlen($team_name) > 100) {
        redirect('/admin/teams/' . $team_id . '/edit?error=' . urlencode('Teamname ist erforderlich (max. 100 Zeichen).'));
    }
    if (!in_array($department_id, $valid_departments, true)) {
        redirect('/admin/teams/' . $team_id . '/edit?error=' . urlencode('Wähle die Abteilung des Teams.'));
    }

    $pdo->prepare("UPDATE teams SET name = ?, sort_order = ?, department_id = ? WHERE id = ?")
        ->execute([$team_name, $sort_order, $department_id, $team_id]);

    redirect('/admin/teams?success=' . urlencode($team_name . ' gespeichert.'));
}

// GET: render edit form
$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

$departments = departments_list($pdo);

render_admin_page('Team bearbeiten', 'teams', function() use ($team, $error, $departments) {
    require ROOT_PATH . '/src/templates/admin/team_edit.php';
});
