<?php
// src/coordinator/members_handler.php — GET /coordinator/members
// Merged view: team member accounts + their linked player records.

declare(strict_types=1);

require_coordinator();

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$uid     = (int)$_SESSION['user_id'];
$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? e($_GET['success']) : '';

// All team members with their linked member records (member_id is NOT NULL after migration 029)
$stmt = $pdo->prepare(
    "SELECT u.id, u.username, u.is_active, u.confirmed_at,
            p.first_name, p.last_name,
            p.id AS member_profile_id, p.email AS player_email, p.phone AS player_phone,
            p.contact_name, p.contact_phone, p.contact_email, p.description,
            c.name AS club_name
     FROM users u
     JOIN members p ON p.id = u.member_id
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE u.role = 'member'
     ORDER BY u.is_active DESC, p.first_name ASC, p.last_name ASC"
);
$stmt->execute();
$members = $stmt->fetchAll();

// Fetch attributes for all linked member profiles in one query
$player_attr_visible = []; // member_profile_id → [[name, value], ...]
$player_attr_hidden  = [];
$linked_player_ids   = array_values(array_filter(array_unique(array_column($members, 'member_profile_id'))));
if (!empty($linked_player_ids)) {
    set_admin_context($pdo);
    $ph = implode(',', array_fill(0, count($linked_player_ids), '?'));
    $attr_stmt = $pdo->prepare(
        "SELECT pav.member_id, pa.name AS attr_name, pa.visible_to_player, pav.value
         FROM member_attribute_values pav
         JOIN member_attributes pa ON pa.id = pav.attribute_id
         WHERE pav.member_id IN ($ph) AND pav.value != ''
         ORDER BY pa.visible_to_player DESC, pa.sort_order ASC, pa.name ASC"
    );
    $attr_stmt->execute($linked_player_ids);
    foreach ($attr_stmt->fetchAll() as $row) {
        $pid = (int)$row['member_id'];
        if ($row['visible_to_player']) {
            $player_attr_visible[$pid][] = ['name' => $row['attr_name'], 'value' => $row['value']];
        } else {
            $player_attr_hidden[$pid][]  = ['name' => $row['attr_name'], 'value' => $row['value']];
        }
    }
    reset_rls_context($pdo);
    set_team_context($pdo, $team_id, 'coordinator', $uid);
}


require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Mitglieder', 'members', function() use (
    $members,
    $player_attr_visible, $player_attr_hidden,
    $error, $success
) {
    require ROOT_PATH . '/src/templates/coordinator/members.php';
});
