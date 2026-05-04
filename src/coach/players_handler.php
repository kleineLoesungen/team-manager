<?php
// src/coach/players_handler.php — GET /coach/players — player list for coach

declare(strict_types=1);

require_coach();

$pdo = get_db();

// RLS enforces team isolation — no explicit team_id filter needed, but add it for clarity
$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, username, is_active
     FROM users
     WHERE role = 'player'
     ORDER BY is_active DESC, first_name, last_name"
);
$stmt->execute();
$players = $stmt->fetchAll();

$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/coach/layout.php';

render_coach_page('Mitglieder', 'players', function() use ($players, $error) {
    if ($error) {
        echo '<div class="alert alert-danger">' . $error . '</div>';
    }
    require ROOT_PATH . '/src/templates/coach/players.php';
});
