<?php
// src/coordinator/member_change_player_handler.php — GET+POST /coordinator/members/{id}/change-player
// Reassign which player record is linked to a member account.
// Two modes: link to an existing playerless record, or create a new player inline.

declare(strict_types=1);

require_coordinator();

$member_id = (int)($_REQUEST['member_id'] ?? 0);
if ($member_id <= 0) redirect('/coordinator/members');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];

// Ownership check — also fetch current player for pre-display
$check = $pdo->prepare(
    "SELECT u.id, u.username, u.player_id,
            p.first_name, p.last_name, p.email AS player_email
     FROM users u
     JOIN players p ON p.id = u.player_id
     WHERE u.id = ? AND u.team_id = ? AND u.role = 'member'"
);
$check->execute([$member_id, $team_id]);
$member = $check->fetch();
if (!$member) redirect('/coordinator/members');

$current_player_id = (int)$member['player_id'];
$from_player       = (int)($_REQUEST['from_player'] ?? 0);
$cancel_url        = $from_player > 0 ? '/coordinator/players/' . $from_player : '/coordinator/members';

// Players not already linked to another user on this team (current member's player is included)
set_admin_context($pdo);
$lp_stmt = $pdo->prepare(
    "SELECT p.id, p.first_name, p.last_name, c.name AS club_name
     FROM players p
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE NOT EXISTS (
         SELECT 1 FROM users u WHERE u.player_id = p.id AND u.team_id = ? AND u.id != ?
     )
     AND NOT EXISTS (
         SELECT 1 FROM users u WHERE u.player_id = p.id AND u.role = 'coordinator'
     )
     ORDER BY p.last_name ASC, p.first_name ASC"
);
$lp_stmt->execute([$team_id, $member_id]);
$linkable_players = $lp_stmt->fetchAll();
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $create_mode = $_POST['create_mode'] ?? 'link';

    if ($create_mode === 'new') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name']  ?? '');
        $email_raw  = trim($_POST['email']      ?? '');

        if (empty($first_name) || empty($last_name)) {
            $error = 'Vor- und Nachname sind erforderlich.';
        } elseif ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } else {
            set_admin_context($pdo);
            $p_stmt = $pdo->prepare(
                "INSERT INTO players (first_name, last_name, email) VALUES (?, ?, ?) RETURNING id"
            );
            $p_stmt->execute([$first_name, $last_name, $email_raw !== '' ? $email_raw : null]);
            $new_player_id = (int)$p_stmt->fetchColumn();

            $pdo->prepare("UPDATE users SET player_id = ? WHERE id = ? AND team_id = ? AND role = 'member'")
                ->execute([$new_player_id, $member_id, $team_id]);
            reset_rls_context($pdo);
            set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

            redirect('/coordinator/members?success=' . urlencode(
                'Spielerprofil für ' . $member['username'] . ' geändert.'
            ));
        }
    } else {
        $new_player_id = (int)($_POST['player_id_link'] ?? 0);

        if ($new_player_id <= 0) {
            $error = 'Bitte wähle einen Spieler aus.';
        } elseif ($new_player_id === $current_player_id) {
            redirect('/coordinator/members');
        } else {
            set_admin_context($pdo);
            // Verify player exists and is not linked to another user
            $p_check = $pdo->prepare(
                "SELECT id FROM players WHERE id = ?"
            );
            $p_check->execute([$new_player_id]);
            if (!$p_check->fetch()) {
                $error = 'Spieler nicht gefunden.';
            } else {
                $dup = $pdo->prepare(
                    "SELECT 1 FROM users WHERE player_id = ? AND team_id = ? AND id != ?"
                );
                $dup->execute([$new_player_id, $team_id, $member_id]);
                if ($dup->fetch()) {
                    $error = 'Dieser Spieler ist bereits Mitglied in diesem Team.';
                }
            }
            reset_rls_context($pdo);
            set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

            if (!$error) {
                $pdo->prepare("UPDATE users SET player_id = ? WHERE id = ? AND team_id = ? AND role = 'member'")
                    ->execute([$new_player_id, $member_id, $team_id]);
                redirect('/coordinator/members?success=' . urlencode(
                    'Spielerprofil für ' . $member['username'] . ' geändert.'
                ));
            }
        }
    }
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page(
    'Spieler verknüpfen — ' . e($member['username']),
    'members',
    function() use ($member, $current_player_id, $linkable_players, $error, $cancel_url) {
        require ROOT_PATH . '/src/templates/coordinator/member_change_player.php';
    }
);
