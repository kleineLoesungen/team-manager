<?php
// src/admin/players_handler.php — GET: list all players with search + club/team filter

declare(strict_types=1);

require_admin();
$pdo = get_db();

require ROOT_PATH . '/src/templates/admin/layout.php';

$search         = trim($_GET['q'] ?? '');
$filter_club_id = (int)($_GET['club_id'] ?? 0);
$filter_team_id = (int)($_GET['team_id'] ?? 0);

$sql    = "SELECT p.id, p.first_name, p.last_name, p.phone, p.contact_name, p.description,
                  c.id AS club_id, c.name AS club_name
           FROM players p
           LEFT JOIN clubs c ON c.id = p.club_id
           WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql     .= " AND (p.first_name ILIKE ? OR p.last_name ILIKE ?)";
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($filter_club_id > 0) {
    $sql     .= " AND p.club_id = ?";
    $params[] = $filter_club_id;
}
if ($filter_team_id > 0) {
    // Team relation lives on users, not team_memberships
    $sql     .= " AND EXISTS (SELECT 1 FROM users u WHERE u.player_id = p.id AND u.team_id = ? AND u.role = 'member')";
    $params[] = $filter_team_id;
}
$sql .= " ORDER BY p.last_name ASC, p.first_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$players = $stmt->fetchAll();

// Fetch all linked member users for the current result set (one query, grouped by player)
$linked_users_map = [];
if (!empty($players)) {
    $player_ids   = array_column($players, 'id');
    $placeholders = implode(',', array_fill(0, count($player_ids), '?'));
    $u_stmt = $pdo->prepare(
        "SELECT u.id, u.player_id, u.username, u.is_active,
                t.id AS team_id, t.name AS team_name, t.is_active AS team_active
         FROM users u
         LEFT JOIN teams t ON t.id = u.team_id
         WHERE u.player_id IN ({$placeholders}) AND u.role = 'member'
         ORDER BY COALESCE(t.is_active, FALSE) DESC, t.name ASC, u.username ASC"
    );
    $u_stmt->execute($player_ids);
    foreach ($u_stmt->fetchAll() as $u) {
        $linked_users_map[$u['player_id']][] = $u;
    }
}

// Unlinked member users for the "add link" dropdown
$unlinked_members = $pdo->query(
    "SELECT u.id, u.username, u.first_name, u.last_name,
            t.name AS team_name, t.is_active AS team_active
     FROM users u
     LEFT JOIN teams t ON t.id = u.team_id
     WHERE u.role = 'member' AND u.player_id IS NULL AND u.is_active = TRUE
     ORDER BY t.name ASC, u.last_name ASC, u.first_name ASC"
)->fetchAll();

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY is_active DESC, name ASC")->fetchAll();

render_admin_page('Spieler', 'players', function() use (
    $players, $clubs, $teams, $linked_users_map, $unlinked_members,
    $search, $filter_club_id, $filter_team_id
) {
    require ROOT_PATH . '/src/templates/admin/players.php';
});
