<?php
// src/coordinator/coordinators_handler.php — GET /coordinator/coordinators — read-only coordinator directory

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

// Show all coordinators across all teams the current coordinator belongs to,
// grouped by team. Requires admin context to cross team boundaries.
set_admin_context($pdo);
$stmt = $pdo->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.phone, u.email,
            t.id AS team_id, t.name AS team_name
     FROM coordinator_teams ct_mine
     JOIN coordinator_teams ct_others ON ct_others.team_id = ct_mine.team_id
          AND ct_others.left_at IS NULL
     JOIN users u ON u.id = ct_others.user_id AND u.is_active = TRUE
     JOIN teams t ON t.id = ct_mine.team_id AND t.is_active = TRUE
     WHERE ct_mine.user_id = ? AND ct_mine.left_at IS NULL
     ORDER BY t.name ASC, u.last_name ASC, u.first_name ASC"
);
$stmt->execute([(int)$_SESSION['user_id']]);
$rows = $stmt->fetchAll();
reset_rls_context($pdo);
set_team_context($pdo, (int)$_SESSION['team_id'], 'coordinator', (int)$_SESSION['user_id']);

// Group by team
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
