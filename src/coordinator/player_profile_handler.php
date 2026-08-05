<?php
// src/coordinator/player_profile_handler.php — GET /coordinator/players/{id}

declare(strict_types=1);

require_coordinator();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
if ($player_id <= 0) redirect('/coordinator/players');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$user_id = (int)$_SESSION['user_id'];

// Fetch player — authorised only if a member user on my team links to it
$p_stmt = $pdo->prepare(
    "SELECT p.*, c.name AS club_name
     FROM players p
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE p.id = ?
       AND EXISTS (
           SELECT 1 FROM users u
           WHERE u.player_id = p.id AND u.team_id = ? AND u.role = 'member'
       )"
);
$p_stmt->execute([$player_id, $team_id]);
$player = $p_stmt->fetch();
if (!$player) redirect('/coordinator/players');

// ── Cross-team data — requires admin context ──────────────────────────────────
set_admin_context($pdo);

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

// Per-team column stats
$team_stats = [];
foreach ($all_linked as $u) {
    $tid = (int)$u['team_id'];
    $uid = (int)$u['user_id'];
    if (isset($team_stats[$tid])) continue; // only first user per team

    $cols_stmt = $pdo->prepare(
        "SELECT id, name, data_type, sort_order FROM columns
         WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
         ORDER BY sort_order, id"
    );
    $cols_stmt->execute([$tid]);
    $cols = $cols_stmt->fetchAll();

    $cells = [];
    $lists = [];
    if (!empty($cols)) {
        $l_stmt = $pdo->prepare(
            "SELECT DISTINCT l.id, l.name, l.date
             FROM lists l
             JOIN list_global_columns lgc ON lgc.list_id = l.id
             JOIN columns c ON c.id = lgc.column_id
                  AND c.team_id = ? AND c.list_id IS NULL AND c.is_active = TRUE
             WHERE l.team_id = ?
             ORDER BY l.date DESC NULLS LAST, l.name ASC"
        );
        $l_stmt->execute([$tid, $tid]);
        $lists = $l_stmt->fetchAll();

        $c_stmt = $pdo->prepare(
            "SELECT ce.list_id, ce.column_id, ce.value
             FROM cells ce
             JOIN lists l ON l.id = ce.list_id AND l.team_id = ?
             WHERE ce.player_id = ?"
        );
        $c_stmt->execute([$tid, $uid]);
        foreach ($c_stmt->fetchAll() as $cell) {
            $cells[(int)$cell['list_id']][(int)$cell['column_id']] = $cell['value'];
        }
    }

    $totals = [];
    foreach ($cols as $col) {
        $cid = (int)$col['id'];
        if ($col['data_type'] === 'number') {
            $sum = 0.0;
            foreach ($cells as $lc) {
                if (isset($lc[$cid]) && $lc[$cid] !== '') $sum += (float)$lc[$cid];
            }
            $totals[$cid] = $sum;
        } else {
            $cnt = 0;
            foreach ($cells as $lc) {
                if (isset($lc[$cid]) && in_array($lc[$cid], ['1', 'true'], true)) $cnt++;
            }
            $totals[$cid] = $cnt;
        }
    }

    $team_stats[$tid] = [
        'team_name'   => $u['team_name'],
        'team_active' => (bool)$u['team_active'],
        'is_my_team'  => $tid === $team_id,
        'cols'        => $cols,
        'lists'       => $lists,
        'cells'       => $cells,
        'totals'      => $totals,
    ];
}

reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', $user_id);
// ─────────────────────────────────────────────────────────────────────────────

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

// Put my team first in team_stats display order
uksort($team_stats, fn($a, $b) => ($b === $team_id) <=> ($a === $team_id));

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Spielerprofil', 'players', function() use (
    $player, $player_id,
    $my_linked, $other_linked, $unlinked_my_members,
    $attr_groups, $team_stats,
    $error, $success
) {
    require ROOT_PATH . '/src/templates/coordinator/player_profile.php';
});
