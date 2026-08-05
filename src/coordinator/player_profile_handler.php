<?php
// src/coordinator/player_profile_handler.php — GET /coordinator/players/{id} — player profile

declare(strict_types=1);

require_coordinator();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
if ($player_id <= 0) redirect('/coordinator/players');

$pdo = get_db();

// Fetch player — verify it's accessible via a member user account on the coordinator's team
$p_stmt = $pdo->prepare(
    "SELECT p.*, c.name AS club_name
     FROM players p
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE p.id = ?
       AND EXISTS (
           SELECT 1 FROM users u
           WHERE u.player_id = p.id
             AND u.team_id = ?
             AND u.role = 'member'
       )"
);
$p_stmt->execute([$player_id, (int)$_SESSION['team_id']]);
$player = $p_stmt->fetch();
if (!$player) redirect('/coordinator/players');

// Team accounts: all user accounts linked to this player (shows which teams they're part of)
$hist_stmt = $pdo->prepare(
    "SELECT t.name AS team_name, u.username, u.is_active, t.is_active AS team_active
     FROM users u
     JOIN teams t ON t.id = u.team_id
     WHERE u.player_id = ?
     ORDER BY t.name ASC"
);
$hist_stmt->execute([$player_id]);
$history = $hist_stmt->fetchAll();

// All attribute groups and values (no visible_to_player filter for coordinator — sees all)
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
$raw_attrs = $attr_stmt->fetchAll();

// Group attributes by group_name for template
$attr_groups = [];
foreach ($raw_attrs as $row) {
    $gname = $row['group_name'];
    if (!isset($attr_groups[$gname])) {
        $attr_groups[$gname] = ['group_order' => $row['group_order'], 'attrs' => []];
    }
    $attr_groups[$gname]['attrs'][] = $row;
}

// Find ALL user accounts linked to this player (one per team is the expected pattern)
$u_stmt = $pdo->prepare("SELECT id FROM users WHERE player_id = ?");
$u_stmt->execute([$player_id]);
$linked_user_ids = array_column($u_stmt->fetchAll(), 'id');
$linked_user_id  = $linked_user_ids[0] ?? false; // for template badge

// Cross-team stats: aggregate cells across ALL linked user accounts
$cross_stats = [];
if (!empty($linked_user_ids)) {
    set_admin_context($pdo);
    $placeholders = implode(',', array_fill(0, count($linked_user_ids), '?'));
    $stats_stmt = $pdo->prepare(
        "SELECT c.name AS col_name, c.data_type, t.name AS team_name, l.date, ce.value
         FROM cells ce
         JOIN lists l ON l.id = ce.list_id
         JOIN teams t ON t.id = l.team_id
         JOIN columns c ON c.id = ce.column_id AND c.list_id IS NULL
         WHERE ce.player_id IN ($placeholders)
         ORDER BY l.date DESC"
    );
    $stats_stmt->execute($linked_user_ids);
    $cross_stats = $stats_stmt->fetchAll();
    // CRITICAL: reset admin context immediately, before any other query
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], 'coordinator', (int)$_SESSION['user_id']);
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Spielerprofil', 'players', function() use ($player, $history, $attr_groups, $cross_stats, $linked_user_id, $player_id) {
    require ROOT_PATH . '/src/templates/coordinator/player_profile.php';
});
