<?php
// src/coach/lists_handler.php — GET /coach/lists — list overview for coach (LIST-01, LIST-04)

declare(strict_types=1);

require_coach();

$pdo = get_db();

// Fetch all lists for this team with column counts; RLS context already set by require_coach()
$stmt = $pdo->prepare(
    "SELECT
        l.id,
        l.name,
        l.visibility,
        l.created_at,
        COUNT(c.id) AS column_count
     FROM lists l
     LEFT JOIN columns c ON (c.list_id = l.id OR (c.list_id IS NULL AND c.team_id = l.team_id))
     WHERE l.team_id = ?
     GROUP BY l.id
     ORDER BY l.created_at DESC"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? 'Liste aktualisiert.' : '';

require ROOT_PATH . '/src/templates/coach/layout.php';

render_coach_page('Listen', 'lists', function() use ($lists, $error, $success) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/coach/lists.php';
});
