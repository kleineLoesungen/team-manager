<?php
// src/coordinator/players_handler.php — GET /coordinator/players

declare(strict_types=1);

require_coordinator();

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$uid     = (int)$_SESSION['user_id'];
$error   = !empty($_GET['error']) ? e($_GET['error']) : '';

// Team-scoped member profile list: only profiles linked to a member user on this team
$stmt = $pdo->prepare(
    "SELECT p.id, p.first_name, p.last_name, p.email, p.phone, p.contact_name, p.contact_phone, p.contact_email,
            c.name AS club_name,
            u.id AS linked_user_id, u.username AS linked_username, u.is_active AS user_active
     FROM users u
     JOIN members p ON p.id = u.member_id
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE u.team_id = ? AND u.role = 'member'
     ORDER BY p.first_name ASC, p.last_name ASC"
);
$stmt->execute([$team_id]);
$players = $stmt->fetchAll();

// Unlinked active members (for the "add" form)
$ul_stmt = $pdo->prepare(
    "SELECT id, username FROM users
     WHERE team_id = ? AND role = 'member' AND member_id IS NULL AND is_active = TRUE
     ORDER BY username ASC"
);
$ul_stmt->execute([$team_id]);
$unlinked_members = $ul_stmt->fetchAll();

// All players not yet linked to any member on this team (for the "add" form)
$linkable_players = [];
if (!empty($unlinked_members)) {
    set_admin_context($pdo);
    $lp_stmt = $pdo->prepare(
        "SELECT p.id, p.first_name, p.last_name, c.name AS club_name
         FROM members p
         LEFT JOIN clubs c ON c.id = p.club_id
         WHERE NOT EXISTS (
             SELECT 1 FROM users u
             WHERE u.member_id = p.id AND u.team_id = ? AND u.role = 'member'
         )
         ORDER BY p.first_name ASC, p.last_name ASC"
    );
    $lp_stmt->execute([$team_id]);
    $linkable_players = $lp_stmt->fetchAll();
    reset_rls_context($pdo);
    set_team_context($pdo, $team_id, 'coordinator', $uid);
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Mitglieder', 'players', function() use (
    $players, $unlinked_members, $linkable_players, $error
) {
    require ROOT_PATH . '/src/templates/coordinator/players.php';
});
