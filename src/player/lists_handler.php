<?php
// src/player/lists_handler.php — GET /player/lists — list overview for player
// Shows public and protected lists; private lists are invisible to players.

declare(strict_types=1);

require_player();

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, created_at
     FROM lists
     WHERE team_id = ? AND visibility IN ('public', 'protected')
     ORDER BY is_hidden ASC, created_at DESC"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = !empty($_GET['success']) ? 'Gespeichert.' : '';

require ROOT_PATH . '/src/templates/player/layout.php';

render_player_page('Listen', 'lists', function() use ($lists, $success) {
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/player/lists.php';
});
