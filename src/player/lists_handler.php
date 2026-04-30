<?php
// src/player/lists_handler.php — GET /player/lists — list overview for player
// Shows public and protected lists; private lists are invisible to players.

declare(strict_types=1);

require_player();

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT
        l.id,
        l.name,
        l.visibility,
        l.created_at,
        COUNT(c.id) AS column_count
     FROM lists l
     LEFT JOIN columns c ON (c.list_id = l.id OR (c.list_id IS NULL AND c.team_id = l.team_id))
     WHERE l.team_id = ? AND l.visibility IN ('public', 'protected')
     GROUP BY l.id
     ORDER BY l.created_at DESC"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = !empty($_GET['success']) ? 'Gespeichert.' : '';

require ROOT_PATH . '/src/templates/player/layout.php';

render_player_page('Listen', 'lists', function() use ($lists, $success) {
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/player/lists.php';
});
