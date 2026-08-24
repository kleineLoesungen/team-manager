<?php
// src/member/coordinators_handler.php — GET /member/coordinators
// Own team's coordinators are always shown.
// When 'show_coordinators_for_members' is enabled, coordinators from all other active teams are shown too.

declare(strict_types=1);

require_member();

$pdo = get_db();

set_admin_context($pdo);

$setting_stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'show_coordinators_for_members'");
$setting_stmt->execute();
$show_all_teams = $setting_stmt->fetchColumn() === 'true';

// Always fetch own team's coordinators
$stmt = $pdo->prepare(
    "SELECT u.id, p.first_name, p.last_name, p.phone, p.email, cl.name AS club_name
     FROM coordinator_teams ct
     JOIN users u ON u.id = ct.user_id AND u.is_active = TRUE
     JOIN members p ON p.id = u.member_id
     LEFT JOIN clubs cl ON cl.id = p.club_id
     WHERE ct.team_id = ? AND ct.left_at IS NULL
     ORDER BY p.first_name ASC, p.last_name ASC"
);
$stmt->execute([$_SESSION['team_id']]);
$coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);

// When enabled, also fetch coordinators from all other active teams (grouped by team)
$other_teams = [];
if ($show_all_teams) {
    $other_stmt = $pdo->prepare(
        "SELECT u.id, p.first_name, p.last_name, p.phone, p.email, cl.name AS club_name,
                t.name AS team_name
         FROM coordinator_teams ct
         JOIN teams t ON t.id = ct.team_id AND t.is_active = TRUE
         JOIN users u ON u.id = ct.user_id AND u.is_active = TRUE
         JOIN members p ON p.id = u.member_id
         LEFT JOIN clubs cl ON cl.id = p.club_id
         WHERE ct.team_id != ? AND ct.left_at IS NULL
         ORDER BY t.name ASC, p.first_name ASC, p.last_name ASC"
    );
    $other_stmt->execute([$_SESSION['team_id']]);
    foreach ($other_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $team = $row['team_name'];
        if (!isset($other_teams[$team])) {
            $other_teams[$team] = [];
        }
        $other_teams[$team][] = $row;
    }
}

reset_rls_context($pdo);
set_team_context($pdo, (int)$_SESSION['team_id'], 'member', (int)$_SESSION['user_id']);

require ROOT_PATH . '/src/templates/member/layout.php';

render_member_page('Koordinatoren', 'profile', function() use ($coordinators, $other_teams) {
    require ROOT_PATH . '/src/templates/member/coordinators.php';
});
