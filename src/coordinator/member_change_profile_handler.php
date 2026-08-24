<?php
// src/coordinator/member_change_profile_handler.php — GET+POST /coordinator/members/{id}/change-profile
// Reassign which member profile record is linked to a member account.

declare(strict_types=1);

require_coordinator();

$member_id = (int)($_REQUEST['member_id'] ?? 0);
if ($member_id <= 0) redirect('/coordinator/members');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];

// Ownership check — also fetch current profile for pre-display
$check = $pdo->prepare(
    "SELECT u.id, u.username, u.member_id,
            p.first_name, p.last_name, p.email AS profile_email
     FROM users u
     JOIN members p ON p.id = u.member_id
     WHERE u.id = ? AND u.team_id = ? AND u.role = 'member'"
);
$check->execute([$member_id, $team_id]);
$member = $check->fetch();
if (!$member) redirect('/coordinator/members');

$current_profile_id = (int)$member['member_id'];
$from_profile       = (int)($_REQUEST['from_profile'] ?? 0);
$cancel_url         = $from_profile > 0 ? '/coordinator/member-profiles/' . $from_profile : '/coordinator/members';

// Profiles not already linked to another user on this team (current member's profile is included)
set_admin_context($pdo);
$lp_stmt = $pdo->prepare(
    "SELECT p.id, p.first_name, p.last_name, c.name AS club_name
     FROM members p
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE NOT EXISTS (
         SELECT 1 FROM users u WHERE u.member_id = p.id AND u.team_id = ? AND u.id != ?
     )
     AND NOT EXISTS (
         SELECT 1 FROM users u WHERE u.member_id = p.id AND u.role = 'coordinator'
     )
     ORDER BY p.first_name ASC, p.last_name ASC"
);
$lp_stmt->execute([$team_id, $member_id]);
$linkable_profiles = $lp_stmt->fetchAll();
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
                "INSERT INTO members (first_name, last_name, email) VALUES (?, ?, ?) RETURNING id"
            );
            $p_stmt->execute([$first_name, $last_name, $email_raw !== '' ? $email_raw : null]);
            $new_profile_id = (int)$p_stmt->fetchColumn();

            $pdo->prepare("UPDATE users SET member_id = ? WHERE id = ? AND team_id = ? AND role = 'member'")
                ->execute([$new_profile_id, $member_id, $team_id]);
            reset_rls_context($pdo);
            set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

            redirect('/coordinator/members?success=' . urlencode(
                'Profil für ' . $member['username'] . ' geändert.'
            ));
        }
    } else {
        $new_profile_id = (int)($_POST['member_id_link'] ?? 0);

        if ($new_profile_id <= 0) {
            $error = 'Bitte wähle ein Profil aus.';
        } elseif ($new_profile_id === $current_profile_id) {
            redirect('/coordinator/members');
        } else {
            set_admin_context($pdo);
            $p_check = $pdo->prepare("SELECT id FROM members WHERE id = ?");
            $p_check->execute([$new_profile_id]);
            if (!$p_check->fetch()) {
                $error = 'Profil nicht gefunden.';
            } else {
                $dup = $pdo->prepare(
                    "SELECT 1 FROM users WHERE member_id = ? AND team_id = ? AND id != ?"
                );
                $dup->execute([$new_profile_id, $team_id, $member_id]);
                if ($dup->fetch()) {
                    $error = 'Dieses Profil ist bereits mit einem Mitglied in diesem Team verknüpft.';
                }
            }
            reset_rls_context($pdo);
            set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

            if (!$error) {
                $pdo->prepare("UPDATE users SET member_id = ? WHERE id = ? AND team_id = ? AND role = 'member'")
                    ->execute([$new_profile_id, $member_id, $team_id]);
                redirect('/coordinator/members?success=' . urlencode(
                    'Profil für ' . $member['username'] . ' geändert.'
                ));
            }
        }
    }
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page(
    'Profil verknüpfen — ' . e($member['username']),
    'members',
    function() use ($member, $current_profile_id, $linkable_profiles, $error, $cancel_url) {
        require ROOT_PATH . '/src/templates/coordinator/member_change_profile.php';
    }
);
