<?php
// src/coach/lists_handler.php — GET /coach/lists — list overview for coach (LIST-01, LIST-04)

declare(strict_types=1);

require_coach();

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, created_at
     FROM lists
     WHERE team_id = ?
     ORDER BY is_hidden ASC, created_at DESC"
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
