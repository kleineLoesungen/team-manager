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
    $selected_team_id = $team_id;

    if (empty($first_name) || empty($last_name)) {
        $error = 'Vor- und Nachname sind erforderlich.';
    } elseif ($team_id <= 0) {
        $error = 'Bitte wähle ein Team aus.';
    } else {
        try {
            // Generate unique username — per D-11: initials + 4-digit number
            $username = generate_unique_username($pdo, $first_name, $last_name);

            // Generate random password
            $plain_password = generate_random_password();
            $password_hash  = password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare(
                "INSERT INTO users (team_id, role, first_name, last_name, username, password_hash)
                 VALUES (?, 'coach', ?, ?, ?, ?)"
            );
            $stmt->execute([$team_id, $first_name, $last_name, $username, $password_hash]);

            // Display credential modal — per AUTH-04 (for coaches, same mechanism)
            // NEVER log $plain_password
            $credential_username = $username;
            $credential_password = $plain_password;
            $redirect_url        = '/admin/coaches';

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

render_admin_page('Moderator hinzufügen', 'coaches', function() use ($teams, $error, $selected_team_id) {
    require ROOT_PATH . '/src/templates/admin/coach_form.php';
});
