<?php
// src/coordinator/member_create_handler.php — GET+POST /coordinator/members/create
// Every new member account must be linked to a player record (NOT NULL after migration 024).
// Two modes: link an existing playerless record, or create a new player inline.

declare(strict_types=1);

require_coordinator();

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
$error   = '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

// Players not yet on this team — a player may be on multiple teams
set_admin_context($pdo);
$lp_stmt = $pdo->prepare(
    "SELECT p.id, p.first_name, p.last_name, c.name AS club_name
     FROM players p
     LEFT JOIN clubs c ON c.id = p.club_id
     WHERE NOT EXISTS (
         SELECT 1 FROM users u WHERE u.player_id = p.id AND u.team_id = ?
     )
     AND NOT EXISTS (
         SELECT 1 FROM users u WHERE u.player_id = p.id AND u.role = 'coordinator'
     )
     ORDER BY p.last_name ASC, p.first_name ASC"
);
$lp_stmt->execute([$team_id]);
$linkable_players = $lp_stmt->fetchAll();
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $create_mode = $_POST['create_mode'] ?? 'new';

    if ($create_mode === 'link') {
        $player_id = (int)($_POST['player_id_link'] ?? 0);
        $linked_player = null;

        if ($player_id <= 0) {
            $error = 'Bitte wähle einen Spieler aus.';
        } else {
            set_admin_context($pdo);
            $p_check = $pdo->prepare(
                "SELECT id, first_name, last_name FROM players WHERE id = ?"
            );
            $p_check->execute([$player_id]);
            $linked_player = $p_check->fetch();

            if (!$linked_player) {
                $error = 'Spieler nicht gefunden.';
            } else {
                $dup = $pdo->prepare("SELECT 1 FROM users WHERE player_id = ? AND team_id = ?");
                $dup->execute([$player_id, $team_id]);
                if ($dup->fetch()) {
                    $error = 'Dieser Spieler ist bereits Mitglied in diesem Team.';
                }
            }
            reset_rls_context($pdo);
            set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);
        }

        if (!$error && $linked_player) {
            try {
                $username       = generate_unique_username($pdo, $linked_player['first_name'], $linked_player['last_name']);
                $plain_password = generate_random_password();
                $password_hash  = password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12]);

                $pdo->prepare(
                    "INSERT INTO users (team_id, role, first_name, last_name, username, password_hash, player_id)
                     VALUES (?, 'member', ?, ?, ?, ?, ?)"
                )->execute([
                    $team_id,
                    $linked_player['first_name'], $linked_player['last_name'],
                    $username, $password_hash, $player_id,
                ]);

                $credential_username = $username;
                $credential_password = $plain_password;
                $redirect_url        = '/coordinator/members';

                render_layout_head('Neue Anmeldedaten');
                render_navbar();
                require ROOT_PATH . '/src/templates/admin/credential_modal.php';
                render_layout_foot();
                exit;
            } catch (PDOException $e) {
                error_log('Member create (link) error: ' . $e->getMessage());
                $error = 'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.';
            }
        }
    } else {
        // New player mode
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name']  ?? '');
        $email_raw  = trim($_POST['email']      ?? '');

        if (empty($first_name) || empty($last_name)) {
            $error = 'Vor- und Nachname sind erforderlich.';
        } elseif ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } else {
            try {
                set_admin_context($pdo);
                $p_stmt = $pdo->prepare(
                    "INSERT INTO players (first_name, last_name, email) VALUES (?, ?, ?) RETURNING id"
                );
                $p_stmt->execute([$first_name, $last_name, $email_raw !== '' ? $email_raw : null]);
                $new_player_id = (int)$p_stmt->fetchColumn();
                reset_rls_context($pdo);
                set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

                $username       = generate_unique_username($pdo, $first_name, $last_name);
                $plain_password = generate_random_password();
                $password_hash  = password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12]);

                $pdo->prepare(
                    "INSERT INTO users (team_id, role, first_name, last_name, username, password_hash, player_id)
                     VALUES (?, 'member', ?, ?, ?, ?, ?)"
                )->execute([$team_id, $first_name, $last_name, $username, $password_hash, $new_player_id]);

                $credential_username = $username;
                $credential_password = $plain_password;
                $redirect_url        = '/coordinator/members';

                render_layout_head('Neue Anmeldedaten');
                render_navbar();
                require ROOT_PATH . '/src/templates/admin/credential_modal.php';
                render_layout_foot();
                exit;
            } catch (PDOException $e) {
                error_log('Member create error: ' . $e->getMessage());
                $error = 'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.';
            }
        }
    }
}

render_coach_page('Neues Mitglied anlegen', 'members', function() use ($error, $linkable_players) {
    require ROOT_PATH . '/src/templates/coordinator/member_form.php';
});
