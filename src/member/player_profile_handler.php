<?php
// src/member/player_profile_handler.php — GET /member/player-profile
// Shows the member's own player record (via users.player_id), attributes filtered
// by visible_to_player, team membership history, and cross-team stats.

declare(strict_types=1);

require_member();

$pdo = get_db();

// Find this member's linked player record
$link_stmt = $pdo->prepare("SELECT player_id FROM users WHERE id = ? LIMIT 1");
$link_stmt->execute([(int)$_SESSION['user_id']]);
$player_id = $link_stmt->fetchColumn();

// If no player is linked, render a "not linked" state (not an error)
$player      = null;
$attr_groups = [];
$cross_stats = [];
$history     = [];

if ($player_id) {
    $player_id = (int)$player_id;

    // Fetch player record + club name
    // RLS: members can read their own player via users.player_id subquery
    set_admin_context($pdo);
    $p_stmt = $pdo->prepare(
        "SELECT p.first_name, p.last_name, p.description,
                c.name AS club_name
         FROM players p
         LEFT JOIN clubs c ON c.id = p.club_id
         WHERE p.id = ?"
    );
    $p_stmt->execute([$player_id]);
    $player = $p_stmt->fetch();

    // Team membership history (all teams, newest first)
    $hist_stmt = $pdo->prepare(
        "SELECT t.name AS team_name, tm.joined_at, tm.left_at
         FROM team_memberships tm
         JOIN teams t ON t.id = tm.team_id
         WHERE tm.player_id = ?
         ORDER BY tm.joined_at DESC"
    );
    $hist_stmt->execute([$player_id]);
    $history = $hist_stmt->fetchAll();

    // Visible attributes only (WHERE visible_to_player = TRUE)
    $attr_stmt = $pdo->prepare(
        "SELECT pag.name AS group_name, pag.sort_order AS group_order,
                pa.id AS attr_id, pa.name AS attr_name, pa.sort_order AS attr_order,
                pa.editable_by_player,
                COALESCE(pav.value, '') AS value
         FROM player_attribute_groups pag
         JOIN player_attributes pa ON pa.group_id = pag.id
         LEFT JOIN player_attribute_values pav ON pav.attribute_id = pa.id AND pav.player_id = ?
         WHERE pa.visible_to_player = TRUE
         ORDER BY pag.sort_order ASC, pag.name ASC, pa.sort_order ASC, pa.name ASC"
    );
    $attr_stmt->execute([$player_id]);
    $raw_attrs = $attr_stmt->fetchAll();

    // Group by group_name for template
    foreach ($raw_attrs as $row) {
        $gname = $row['group_name'];
        if (!isset($attr_groups[$gname])) {
            $attr_groups[$gname] = ['group_order' => $row['group_order'], 'attrs' => []];
        }
        $attr_groups[$gname]['attrs'][] = $row;
    }

    // Cross-team stats: use this member's own user_id (member IS the linked user)
    // set_admin_context is already active from above — reuse it for the stats query
    $stats_stmt = $pdo->prepare(
        "SELECT c.name AS col_name, c.data_type, t.name AS team_name, l.date, ce.value
         FROM cells ce
         JOIN lists l ON l.id = ce.list_id
         JOIN teams t ON t.id = l.team_id
         JOIN columns c ON c.id = ce.column_id AND c.list_id IS NULL
         WHERE ce.player_id = :user_id
         ORDER BY l.date DESC"
    );
    $stats_stmt->execute([':user_id' => (int)$_SESSION['user_id']]);
    $cross_stats = $stats_stmt->fetchAll();

    // CRITICAL: reset admin context immediately, restore member team context
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], 'member', (int)$_SESSION['user_id']);
}

require ROOT_PATH . '/src/templates/member/layout.php';

render_player_page('Mein Profil', 'player_profile', function() use ($player, $player_id, $attr_groups, $cross_stats, $history) {
    require ROOT_PATH . '/src/templates/member/player_profile.php';
});
