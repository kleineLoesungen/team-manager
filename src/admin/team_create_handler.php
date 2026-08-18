<?php
// src/admin/team_create_handler.php — GET: show create form; POST: create a new team

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $team_name  = trim($_POST['team_name']  ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (empty($team_name) || strlen($team_name) > 100) {
        redirect('/admin/teams/create?error=' . urlencode('Teamname ist erforderlich (max. 100 Zeichen).'));
    }

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare("INSERT INTO teams (name, sort_order) VALUES (?, ?)");
        $stmt->execute([$team_name, $sort_order]);
        redirect('/admin/teams?success=' . urlencode($team_name . ' erstellt.'));
    } catch (PDOException $e) {
        error_log('Team create error: ' . $e->getMessage());
        redirect('/admin/teams/create?error=' . urlencode('Ein Fehler ist aufgetreten. Bitte versuch es später erneut.'));
    }
}

// GET: render create form
$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Team erstellen', 'teams', function() use ($error) {
    require ROOT_PATH . '/src/templates/admin/team_create.php';
});
