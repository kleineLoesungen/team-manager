<?php
// src/admin/players_handler.php — GET: list all players with search + club/team filter

declare(strict_types=1);

require_admin();
$pdo = get_db();

require ROOT_PATH . '/src/templates/admin/layout.php';

$search         = trim($_GET['q'] ?? '');
$filter_club_id = (int)($_GET['club_id'] ?? 0);
$filter_team_id = (int)($_GET['team_id'] ?? 0);

$sql    = "SELECT p.id, p.first_name, p.last_name, p.email, p.phone, p.contact_name, p.contact_phone, p.contact_email, p.description,
                  p.is_active,
                  c.id AS club_id, c.name AS club_name
           FROM players p
           LEFT JOIN clubs c ON c.id = p.club_id
           WHERE NOT EXISTS (
               SELECT 1 FROM users u WHERE u.player_id = p.id AND u.role = 'coordinator'
           )";
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
$all_players     = $stmt->fetchAll();
$players         = array_values(array_filter($all_players, fn($p) => (bool)$p['is_active']));
$inactive_players = array_values(array_filter($all_players, fn($p) => !(bool)$p['is_active']));

// Fetch linked member accounts for the current result set (grouped by player)
$linked_users_map = [];
if (!empty($all_players)) {
    $player_ids   = array_column($all_players, 'id');
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

// Unlinked active member users grouped by team — for the two-step team→user link UI
// After migration 024, player_id is NOT NULL for all users, so this always returns empty.
// Kept for UI compatibility; personal data columns now come from players via JOIN.
$unlinked_rows = [];

$unlinked_by_team = [];
foreach ($unlinked_rows as $m) {
    $tid = (int)$m['team_id'];
    if (!isset($unlinked_by_team[$tid])) {
        $unlinked_by_team[$tid] = [
            'team_name'   => $m['team_name'],
            'team_active' => (bool)$m['team_active'],
            'members'     => [],
        ];
    }
    $unlinked_by_team[$tid]['members'][] = [
        'id'         => (int)$m['id'],
        'username'   => $m['username'],
        'first_name' => $m['first_name'],
        'last_name'  => $m['last_name'],
    ];
}
$has_unlinked = !empty($unlinked_by_team);

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();
$teams = $pdo->query("SELECT id, name, is_active FROM teams ORDER BY is_active DESC, sort_order ASC, name ASC")->fetchAll();

render_admin_page('Spieler', 'players', function() use (
    $players, $inactive_players, $clubs, $teams, $linked_users_map, $unlinked_by_team, $has_unlinked,
    $search, $filter_club_id, $filter_team_id
) {
    require ROOT_PATH . '/src/templates/admin/players.php';
});
