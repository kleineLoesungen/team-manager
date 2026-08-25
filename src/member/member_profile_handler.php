<?php
// src/member/member_profile_handler.php — GET /member/member-profile
// Shows the member's own profile record (via users.member_id), attributes filtered
// by visible_to_player, team membership history, and cross-team stats.

declare(strict_types=1);

require_member();

$pdo = get_db();

// Find this member's linked profile record
$link_stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ? LIMIT 1");
$link_stmt->execute([(int)$_SESSION['user_id']]);
$member_id = $link_stmt->fetchColumn();

// If no profile is linked, render a "not linked" state (not an error)
$player           = null;
$attr_groups      = [];
$system_stats     = [];
$coordinator_stats = [];
$history          = [];

if ($member_id) {
    $member_id = (int)$member_id;

    // Fetch profile record + club name
    // RLS: members can read their own profile via users.member_id subquery
    set_admin_context($pdo);
    $p_stmt = $pdo->prepare(
        "SELECT p.first_name, p.last_name, p.description,
                c.name AS club_name
         FROM members p
         LEFT JOIN clubs c ON c.id = p.club_id
         WHERE p.id = ?"
    );
    $p_stmt->execute([$member_id]);
    $player = $p_stmt->fetch();

    // Team accounts: all user accounts linked to this profile
    $hist_stmt = $pdo->prepare(
        "SELECT t.name AS team_name, u.username, u.is_active, t.is_active AS team_active
         FROM users u
         JOIN teams t ON t.id = u.team_id
         WHERE u.member_id = ?
         ORDER BY t.name ASC"
    );
    $hist_stmt->execute([$member_id]);
    $history = $hist_stmt->fetchAll();

    // Visible attributes only (WHERE visible_to_player = TRUE)
    $attr_stmt = $pdo->prepare(
        "SELECT pag.name AS group_name, pag.sort_order AS group_order,
                pa.id AS attr_id, pa.name AS attr_name, pa.sort_order AS attr_order,
                pa.editable_by_player, pa.data_type,
                COALESCE(pav.value, '') AS value
         FROM member_attribute_groups pag
         JOIN member_attributes pa ON pa.group_id = pag.id
         LEFT JOIN member_attribute_values pav ON pav.attribute_id = pa.id AND pav.member_id = ?
         WHERE pa.visible_to_player = TRUE
         ORDER BY pag.sort_order ASC, pag.name ASC, pa.sort_order ASC, pa.name ASC"
    );
    $attr_stmt->execute([$member_id]);
    $raw_attrs = $attr_stmt->fetchAll();

    // Group by group_name for template
    foreach ($raw_attrs as $row) {
        $gname = $row['group_name'];
        if (!isset($attr_groups[$gname])) {
            $attr_groups[$gname] = ['group_order' => $row['group_order'], 'attrs' => []];
        }
        $attr_groups[$gname]['attrs'][] = $row;
    }

    // Cross-team stats: aggregate cells across ALL user accounts linked to this profile
    // Re-query user IDs since history has usernames not IDs
    $uid_stmt = $pdo->prepare("SELECT id FROM users WHERE member_id = ?");
    $uid_stmt->execute([$member_id]);
    $all_user_ids = array_column($uid_stmt->fetchAll(), 'id');
    // Ensure current user is included even if query returned 0 rows
    if (!in_array((int)$_SESSION['user_id'], $all_user_ids)) {
        $all_user_ids[] = (int)$_SESSION['user_id'];
    }
    $placeholders = implode(',', array_fill(0, count($all_user_ids), '?'));

    // System columns query — cross-team, consistent column names
    $sys_stmt = $pdo->prepare(
        "SELECT c.name AS col_name, c.data_type, t.name AS team_name, l.date, ce.value
         FROM cells ce
         JOIN lists l ON l.id = ce.list_id
         JOIN teams t ON t.id = l.team_id
         JOIN columns c ON c.id = ce.column_id AND c.list_id IS NULL AND c.is_system = TRUE
         WHERE ce.member_id IN ($placeholders)
           AND (l.date IS NULL OR l.date <= CURRENT_DATE)
         ORDER BY c.sort_order ASC, c.name ASC, l.date DESC"
    );
    $sys_stmt->execute($all_user_ids);
    $system_stats = $sys_stmt->fetchAll();

    // Coordinator global columns query — team-scoped, shown in collapsible section
    $coord_stmt = $pdo->prepare(
        "SELECT c.name AS col_name, c.data_type, t.name AS team_name, l.date, ce.value
         FROM cells ce
         JOIN lists l ON l.id = ce.list_id
         JOIN teams t ON t.id = l.team_id
         JOIN columns c ON c.id = ce.column_id AND c.list_id IS NULL AND c.is_system = FALSE
         WHERE ce.member_id IN ($placeholders)
           AND (l.date IS NULL OR l.date <= CURRENT_DATE)
         ORDER BY c.name ASC, c.data_type ASC, l.date DESC"
    );
    $coord_stmt->execute($all_user_ids);
    $coordinator_stats = $coord_stmt->fetchAll();

    // CRITICAL: reset admin context immediately, restore member team context
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], 'member', (int)$_SESSION['user_id']);
}

require ROOT_PATH . '/src/templates/member/layout.php';

render_member_page('Mein Verlauf', 'member_profile', function() use ($player, $member_id, $attr_groups, $system_stats, $coordinator_stats, $history) {
    require ROOT_PATH . '/src/templates/member/member_profile.php';
});
