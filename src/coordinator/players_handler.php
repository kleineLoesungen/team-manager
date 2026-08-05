<?php
// src/coordinator/players_handler.php — GET /coordinator/players

declare(strict_types=1);

require_coordinator();

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$uid     = (int)$_SESSION['user_id'];
$error   = !empty($_GET['error']) ? e($_GET['error']) : '';

// Team-scoped player list: only players linked to a member user on this team
$stmt = $pdo->prepare(
    "SELECT p.id, p.first_name, p.last_name, p.phone, p.contact_name,
            c.name AS club_name,
            u.id AS linked_user_id, u.username AS linked_username, u.is_active AS user_active
     FROM users u
     JOIN players p ON p.id = u.player_id
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE u.team_id = ? AND u.role = 'member'
     ORDER BY p.last_name ASC, p.first_name ASC"
);
$stmt->execute([$team_id]);
$players = $stmt->fetchAll();

// Unlinked active members (for the "add" form)
$ul_stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, username FROM users
     WHERE team_id = ? AND role = 'member' AND player_id IS NULL AND is_active = TRUE
     ORDER BY last_name ASC, first_name ASC"
);
$ul_stmt->execute([$team_id]);
$unlinked_members = $ul_stmt->fetchAll();

// All players not yet linked to any member on this team (for the "add" form)
$linkable_players = [];
if (!empty($unlinked_members)) {
    set_admin_context($pdo);
    $lp_stmt = $pdo->prepare(
        "SELECT p.id, p.first_name, p.last_name, c.name AS club_name
         FROM players p
         LEFT JOIN clubs c ON c.id = p.club_id
         WHERE NOT EXISTS (
             SELECT 1 FROM users u
             WHERE u.player_id = p.id AND u.team_id = ? AND u.role = 'member'
         )
         ORDER BY p.last_name ASC, p.first_name ASC"
    );
    $lp_stmt->execute([$team_id]);
    $linkable_players = $lp_stmt->fetchAll();
    reset_rls_context($pdo);
    set_team_context($pdo, $team_id, 'coordinator', $uid);
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Spieler', 'players', function() use (
    $players, $unlinked_members, $linkable_players, $error
) {
    require ROOT_PATH . '/src/templates/coordinator/players.php';
});
