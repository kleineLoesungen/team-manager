<?php
// src/coordinator/coordinators_handler.php — GET /coordinator/coordinators — read-only coordinator directory

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

// Show all coordinators across all teams the current coordinator belongs to,
// grouped by team. Requires admin context to cross team boundaries.
set_admin_context($pdo);
$stmt = $pdo->query(
    "SELECT u.id, p.first_name, p.last_name, p.phone, p.email,
            cl.name AS club_name,
            t.id AS team_id, t.name AS team_name, t.sort_order AS team_sort_order
     FROM coordinator_teams ct
     JOIN users u ON u.id = ct.user_id AND u.is_active = TRUE
     JOIN players p ON p.id = u.player_id
     LEFT JOIN clubs cl ON cl.id = p.club_id
     JOIN teams t ON t.id = ct.team_id AND t.is_active = TRUE
     WHERE ct.left_at IS NULL
     ORDER BY t.sort_order ASC, t.name ASC, p.last_name ASC, p.first_name ASC"
);
$rows = $stmt->fetchAll();
reset_rls_context($pdo);
set_team_context($pdo, (int)$_SESSION['team_id'], 'coordinator', (int)$_SESSION['user_id']);

// Group by team — ORDER BY in query already guarantees sort_order
$teams_map = [];
foreach ($rows as $row) {
    $tid = $row['team_id'];
    if (!isset($teams_map[$tid])) {
        $teams_map[$tid] = ['team_name' => $row['team_name'], 'coordinators' => []];
    }
    $teams_map[$tid]['coordinators'][] = $row;
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Koordinatoren', 'coordinators', function() use ($teams_map) {
    require ROOT_PATH . '/src/templates/coordinator/coordinators.php';
});
