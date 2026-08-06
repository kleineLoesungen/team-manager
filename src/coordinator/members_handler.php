<?php
// src/coordinator/members_handler.php — GET /coordinator/members
// Merged view: team member accounts + their linked player records.

declare(strict_types=1);

require_coordinator();

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$uid     = (int)$_SESSION['user_id'];
$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? e($_GET['success']) : '';

// All team members with optional player link (RLS scopes to team via app.current_team_id)
$stmt = $pdo->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.username, u.is_active, u.email,
            p.id AS player_id, p.first_name AS player_first, p.last_name AS player_last,
            c.name AS club_name
     FROM users u
     LEFT JOIN players p ON p.id = u.player_id
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE u.role = 'member'
     ORDER BY u.is_active DESC, u.last_name ASC, u.first_name ASC"
);
$stmt->execute();
$members = $stmt->fetchAll();

// Unlinked active members and linkable players — used by the "Spieler verknüpfen" form
$unlinked_members = array_values(array_filter($members, fn($m) => $m['is_active'] && $m['player_id'] === null));

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

render_coach_page('Mitglieder', 'members', function() use (
    $members, $unlinked_members, $linkable_players, $error, $success
) {
    require ROOT_PATH . '/src/templates/coordinator/members.php';
});
