<?php
// src/coordinator/players_handler.php — GET /coordinator/players — player list for coordinator

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

// Players are not assigned to teams directly — they belong to clubs.
// The link is: member user account on this team → users.player_id → player.
// A player may be linked to multiple user accounts across teams.
$stmt = $pdo->prepare(
    "SELECT DISTINCT p.id, p.first_name, p.last_name, p.phone, p.contact_name,
            c.name AS club_name,
            u.id AS linked_user_id, u.username AS linked_username, u.is_active AS user_active
     FROM users u
     JOIN players p ON p.id = u.player_id
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE u.team_id = ?
       AND u.role = 'member'
     ORDER BY p.last_name ASC, p.first_name ASC"
);
$stmt->execute([(int)$_SESSION['team_id']]);
$players = $stmt->fetchAll();

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Spieler', 'players', function() use ($players) {
    require ROOT_PATH . '/src/templates/coordinator/players.php';
});
