<?php
// src/auth/login_handler.php — Login GET/POST handler
// Handles both admin (config.php) and regular users (DB)

declare(strict_types=1);

require_once ROOT_PATH . '/src/templates/layout.php';

// Redirect already-authenticated users
if (is_admin()) {
    redirect('/admin');
}
if (is_authenticated()) {
    // Role-based redirect for already-authenticated users — per D-02
    if (($_SESSION['role'] ?? '') === 'coordinator') {
        redirect('/coordinator/members');
    } else {
        redirect('/member/lists');
    }
}

$error   = '';
$message = e($_GET['message'] ?? '');  // e.g., session-expired message

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Benutzername oder Passwort falsch. Versuch es erneut.';
    } else {
        $authenticated = false;

        // ── Admin login (config.php) — per D-02 ──────────────────────
        if ($username === ADMIN_USERNAME && !empty(ADMIN_PASSWORD_HASH)) {
            if (password_verify($password, ADMIN_PASSWORD_HASH)) {
                $authenticated = true;
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['is_admin']      = true;
                $_SESSION['role']          = 'admin';
                $_SESSION['last_activity'] = time();
                redirect('/admin');
            }
        }

        // ── Regular user login (DB) ────────────────────────────────
        if (!$authenticated) {
            try {
                $pdo = get_db();
                // RLS blocks unauthenticated reads — temporarily bypass for credential lookup
                set_admin_context($pdo);
                $stmt = $pdo->prepare(
                    "SELECT id, team_id, role, first_name, last_name, is_active, password_hash
                     FROM users WHERE username = ?"
                );
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                // Reset immediately — team context will be set after successful auth
                reset_rls_context($pdo);

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Block inactive users — per D-03
                    if (!$user['is_active']) {
                        $error = 'Dein Konto ist deaktiviert. Bitte wende dich an deinen Koordinator.';
                    } else {
                        session_regenerate_id(true); // Prevent session fixation

                        // Set RLS context before any further queries
                        set_team_context($pdo, (int)$user['team_id']);

                        // Fetch team name for display in navbar
                        $team_stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
                        $team_stmt->execute([$user['team_id']]);
                        $team_row = $team_stmt->fetch();

                        // Map legacy role values in case DB migrations haven't fully run
                        $role = $user['role'];
                        if ($role === 'coach')     $role = 'coordinator';
                        if ($role === 'moderator') $role = 'coordinator';
                        if ($role === 'player')    $role = 'member';
                        if ($role === 'mitglied')  $role = 'member';

                        $_SESSION['user_id']       = $user['id'];
                        $_SESSION['team_id']       = $user['team_id'];
                        $_SESSION['role']          = $role;
                        $_SESSION['display_name']  = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['team_name']     = $team_row ? $team_row['name'] : '';
                        $_SESSION['last_activity'] = time();

                        // Role-based redirect — per D-02
                        if ($role === 'coordinator') {
                            redirect('/coordinator/members');
                        } else {
                            redirect('/member/lists');
                        }
                    }
                } else {
                    // Deliberately vague — do not reveal which field failed
                    $error = 'Benutzername oder Passwort falsch. Versuch es erneut.';
                }
            } catch (PDOException $e) {
                // Log without credentials, per Pitfall 2
                error_log('Login DB error: ' . $e->getMessage());
                $error = 'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.';
            }
        }
    }
}

// Render login page
render_login_page($error, $message);
