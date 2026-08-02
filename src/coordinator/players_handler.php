<?php
// src/coordinator/players_handler.php — GET /coordinator/players — player list for coordinator

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

// RLS already set by require_coordinator() — team context active.
// Query players on current team via team_memberships (left_at IS NULL = active membership).
// NOTE: do NOT add team_id to players table directly (Anti-Pattern from RESEARCH.md).
$stmt = $pdo->prepare(
    "SELECT p.id, p.first_name, p.last_name, p.phone, p.contact_name,
            c.name AS club_name,
            u.id AS linked_user_id, u.username AS linked_username, u.is_active AS user_active
     FROM players p
     LEFT JOIN clubs c ON c.id = p.club_id
     LEFT JOIN team_memberships tm ON tm.player_id = p.id AND tm.left_at IS NULL
                AND tm.team_id = ?
     LEFT JOIN users u ON u.player_id = p.id
     WHERE tm.player_id IS NOT NULL
     ORDER BY p.last_name ASC, p.first_name ASC"
);
$stmt->execute([(int)$_SESSION['team_id']]);
$players = $stmt->fetchAll();

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Spieler', 'players', function() use ($players) {
    require ROOT_PATH . '/src/templates/coordinator/players.php';
});
