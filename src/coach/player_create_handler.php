<?php
// src/coach/player_create_handler.php — GET: show form; POST: create player (TEAM-04)

declare(strict_types=1);

require_coach();

$pdo   = get_db();
$error = '';

require ROOT_PATH . '/src/templates/coach/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');

    if (empty($first_name) || empty($last_name)) {
        $error = 'Vor- und Nachname sind erforderlich.';
    } else {
        try {
            $team_id = (int)$_SESSION['team_id'];

            // Auto-generate username — per D-11 (initials + 4-digit number, collision-checked)
            $username = generate_unique_username($pdo, $first_name, $last_name);

            // Auto-generate password — NEVER log the plaintext
            $plain_password = generate_random_password();
            $password_hash  = password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare(
                "INSERT INTO users (team_id, role, first_name, last_name, username, password_hash)
                 VALUES (?, 'member', ?, ?, ?, ?)"
            );
            $stmt->execute([$team_id, $first_name, $last_name, $username, $password_hash]);

            // Show credential modal — per D-08 (reuse admin pattern)
            // NEVER log $plain_password — per Pitfall 2
            $credential_username = $username;
            $credential_password = $plain_password;
            $redirect_url        = '/moderator/members';

            render_layout_head('Neue Anmeldedaten');
            render_navbar();
            require ROOT_PATH . '/src/templates/admin/credential_modal.php';
            render_layout_foot();
            exit;

        } catch (PDOException $e) {
            error_log('Player create error: ' . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
    }
}

render_coach_page('Neues Mitglied anlegen', 'players', function() use ($error) {
    require ROOT_PATH . '/src/templates/coach/player_form.php';
});
