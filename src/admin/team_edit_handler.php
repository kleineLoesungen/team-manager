<?php
// src/admin/team_edit_handler.php — GET: show edit form; POST: update team
// $_REQUEST['team_id'] set by router

declare(strict_types=1);

require_admin();

$team_id = (int)($_REQUEST['team_id'] ?? 0);
if ($team_id <= 0) {
    redirect('/admin/teams');
}

$pdo  = get_db();
$stmt = $pdo->prepare("SELECT id, name, sort_order FROM teams WHERE id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    redirect('/admin/teams');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $team_name  = trim($_POST['team_name']  ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (empty($team_name) || strlen($team_name) > 100) {
        redirect('/admin/teams/' . $team_id . '/edit?error=' . urlencode('Teamname ist erforderlich (max. 100 Zeichen).'));
    }

    $pdo->prepare("UPDATE teams SET name = ?, sort_order = ? WHERE id = ?")
        ->execute([$team_name, $sort_order, $team_id]);

    redirect('/admin/teams?success=' . urlencode($team_name . ' gespeichert.'));
}

// GET: render edit form
$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Team bearbeiten', 'teams', function() use ($team, $error) {
    require ROOT_PATH . '/src/templates/admin/team_edit.php';
});
