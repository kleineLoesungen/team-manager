<?php
// src/admin/team_create_handler.php — POST: create a new team (TEAM-01)

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/teams');
}

require_csrf();

$team_name = trim($_POST['team_name'] ?? '');

if (empty($team_name) || strlen($team_name) > 100) {
    redirect('/admin/teams?error=' . urlencode('Teamname ist erforderlich (max. 100 Zeichen).'));
}

try {
    $pdo  = get_db();
    $stmt = $pdo->prepare("INSERT INTO teams (name) VALUES (?)");
    $stmt->execute([$team_name]);
    redirect('/admin/teams');
} catch (PDOException $e) {
    error_log('Team create error: ' . $e->getMessage());
    redirect('/admin/teams?error=' . urlencode('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.'));
}
