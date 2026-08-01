<?php
// src/admin/players_handler.php — GET: list all players with club/team filter

declare(strict_types=1);

require_admin();
$pdo = get_db();

$filter_club_id = (int)($_GET['club_id'] ?? 0);
$filter_team_id = (int)($_GET['team_id'] ?? 0);

$sql = "SELECT p.id, p.first_name, p.last_name, p.phone, p.contact_name,
               c.name AS club_name, c.id AS club_id,
               t.name AS current_team_name, t.id AS current_team_id,
               u.id AS linked_user_id, u.username AS linked_username
        FROM players p
        LEFT JOIN clubs c ON c.id = p.club_id
        LEFT JOIN team_memberships tm ON tm.player_id = p.id AND tm.left_at IS NULL
        LEFT JOIN teams t ON t.id = tm.team_id
        LEFT JOIN users u ON u.player_id = p.id
        WHERE 1=1";
$params = [];
if ($filter_club_id > 0) { $sql .= " AND p.club_id = ?"; $params[] = $filter_club_id; }
if ($filter_team_id > 0) { $sql .= " AND tm.team_id = ?"; $params[] = $filter_team_id; }
$sql .= " ORDER BY p.last_name ASC, p.first_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$players = $stmt->fetchAll();

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();
$teams = $pdo->query("SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY name")->fetchAll();

render_admin_page('Spieler', 'players', function() use ($players, $clubs, $teams, $filter_club_id, $filter_team_id) {
    require ROOT_PATH . '/src/templates/admin/players.php';
});
