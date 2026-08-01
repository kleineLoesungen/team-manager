<?php
// src/admin/coach_create_handler.php — GET: show form; POST: create coach (TEAM-02)

declare(strict_types=1);

require_admin();

$pdo = get_db();
$teams_stmt = $pdo->query("SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY name");
$teams = $teams_stmt->fetchAll();

$error = '';
$selected_team_id = null;

require ROOT_PATH . '/src/templates/admin/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $team_id    = (int)($_POST['team_id'] ?? 0);
    $email_raw  = trim($_POST['email'] ?? '');
    $selected_team_id = $team_id;

    if (empty($first_name) || empty($last_name)) {
        $error = 'Vor- und Nachname sind erforderlich.';
    } elseif ($team_id <= 0) {
        $error = 'Bitte wähle ein Team aus.';
    } elseif ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse.';
    } elseif ($email_raw !== '' && mb_strlen($email_raw) > 255) {
        $error = 'E-Mail-Adresse zu lang (max. 255 Zeichen).';
    } else {
        try {
            // Generate unique username — per D-11: initials + 4-digit number
            $username = generate_unique_username($pdo, $first_name, $last_name);

            // Generate random password
            $plain_password = generate_random_password();
            $password_hash  = password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare(
                "INSERT INTO users (team_id, role, first_name, last_name, username, password_hash, email)
                 VALUES (?, 'coordinator', ?, ?, ?, ?, ?)
                 RETURNING id"
            );
            $stmt->execute([$team_id, $first_name, $last_name, $username, $password_hash,
                            $email_raw !== '' ? $email_raw : null]);
            $new_user_id = (int)$stmt->fetchColumn();

            // Phase 8: also record in coordinator_teams (keeps multi-team history)
            $ct_stmt = $pdo->prepare(
                "INSERT INTO coordinator_teams (user_id, team_id, joined_at)
                 VALUES (?, ?, NOW())"
            );
            $ct_stmt->execute([$new_user_id, $team_id]);

            // Display credential modal — per AUTH-04 (for coaches, same mechanism)
            // NEVER log $plain_password
            $credential_username = $username;
            $credential_password = $plain_password;
            $redirect_url        = '/admin/coordinators';

            render_layout_head('Neue Anmeldedaten');
            render_navbar();
            require ROOT_PATH . '/src/templates/admin/credential_modal.php';
            render_layout_foot();
            exit;

        } catch (PDOException $e) {
            error_log('Coach create error: ' . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.';
        }
    }
}

render_admin_page('Koordinator hinzufügen', 'coordinators', function() use ($teams, $error, $selected_team_id) {
    require ROOT_PATH . '/src/templates/admin/coach_form.php';
});
