<?php
// src/admin/club_edit_handler.php — GET: show edit form; POST: update club
// $_REQUEST['club_id'] set by router

declare(strict_types=1);

require_admin();

$club_id = (int)($_REQUEST['club_id'] ?? 0);
if ($club_id <= 0) {
    redirect('/admin/clubs');
}

$pdo  = get_db();
$stmt = $pdo->prepare("SELECT id, name FROM clubs WHERE id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch();

if (!$club) {
    redirect('/admin/clubs');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/clubs/' . $club_id . '/edit?error=' . urlencode('Klubname ist erforderlich (max. 100 Zeichen).'));
    }

    $pdo->prepare("UPDATE clubs SET name = ? WHERE id = ?")
        ->execute([$name, $club_id]);

    redirect('/admin/clubs?success=' . urlencode($name . ' gespeichert.'));
}

// GET: render edit form
$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Klub bearbeiten', 'clubs', function() use ($club, $error) {
    require ROOT_PATH . '/src/templates/admin/club_edit.php';
});
