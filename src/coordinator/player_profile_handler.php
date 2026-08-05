<?php
// src/coordinator/player_profile_handler.php — GET /coordinator/players/{id}

declare(strict_types=1);

require_coordinator();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
if ($player_id <= 0) redirect('/coordinator/players');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$user_id = (int)$_SESSION['user_id'];

// Admin context for the player fetch — players_select RLS only shows already-linked players,
// but coordinators must be able to view any player to link it.
set_admin_context($pdo);

$p_stmt = $pdo->prepare(
    "SELECT p.*, c.name AS club_name
     FROM players p
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE p.id = ?"
);
$p_stmt->execute([$player_id]);
$player = $p_stmt->fetch();
if (!$player) redirect('/coordinator/players');

// All linked user accounts (every team)
$al_stmt = $pdo->prepare(
    "SELECT u.id AS user_id, u.username, u.is_active AS user_active,
            t.id AS team_id, t.name AS team_name, t.is_active AS team_active
     FROM users u
     JOIN teams t ON t.id = u.team_id
     WHERE u.player_id = ? AND u.role = 'member'
     ORDER BY t.name ASC, u.username ASC"
);
$al_stmt->execute([$player_id]);
$all_linked = $al_stmt->fetchAll();

// Cross-team stats: flat list for column-switcher view (≤ today only)
$all_user_ids = array_column($all_linked, 'user_id');
$cross_stats  = [];
if (!empty($all_user_ids)) {
    $placeholders = implode(',', array_fill(0, count($all_user_ids), '?'));
    $cs_stmt = $pdo->prepare(
        "SELECT c.name AS col_name, c.data_type, t.name AS team_name, l.date, ce.value
         FROM cells ce
         JOIN lists l ON l.id = ce.list_id
         JOIN teams t ON t.id = l.team_id
         JOIN columns c ON c.id = ce.column_id AND c.list_id IS NULL
         WHERE ce.player_id IN ($placeholders)
           AND (l.date IS NULL OR l.date <= CURRENT_DATE)
         ORDER BY l.date DESC"
    );
    $cs_stmt->execute($all_user_ids);
    $cross_stats = $cs_stmt->fetchAll();
}

reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', $user_id);

$my_linked    = array_values(array_filter($all_linked, fn($u) => (int)$u['team_id'] === $team_id));
$other_linked = array_values(array_filter($all_linked, fn($u) => (int)$u['team_id'] !== $team_id));

// Members on my team not yet linked to any player (for add-link form)
$ul_stmt = $pdo->prepare(
    "SELECT id, username FROM users
     WHERE team_id = ? AND role = 'member' AND player_id IS NULL AND is_active = TRUE
     ORDER BY username ASC"
);
$ul_stmt->execute([$team_id]);
$unlinked_my_members = $ul_stmt->fetchAll();

// Attribute groups + values
$attr_stmt = $pdo->prepare(
    "SELECT pag.name AS group_name, pag.sort_order AS group_order,
            pa.id AS attr_id, pa.name AS attr_name, pa.sort_order AS attr_order,
            pa.visible_to_player, pa.editable_by_player,
            COALESCE(pav.value, '') AS value
     FROM player_attribute_groups pag
     JOIN player_attributes pa ON pa.group_id = pag.id
     LEFT JOIN player_attribute_values pav ON pav.attribute_id = pa.id AND pav.player_id = ?
     ORDER BY pag.sort_order ASC, pag.name ASC, pa.sort_order ASC, pa.name ASC"
);
$attr_stmt->execute([$player_id]);
$attr_groups = [];
foreach ($attr_stmt->fetchAll() as $row) {
    $gname = $row['group_name'];
    if (!isset($attr_groups[$gname])) {
        $attr_groups[$gname] = ['group_order' => $row['group_order'], 'attrs' => []];
    }
    $attr_groups[$gname]['attrs'][] = $row;
}

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? e($_GET['success']) : '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Spielerprofil', 'players', function() use (
    $player, $player_id,
    $my_linked, $other_linked, $unlinked_my_members,
    $attr_groups, $cross_stats,
    $error, $success
) {
    require ROOT_PATH . '/src/templates/coordinator/player_profile.php';
});
