<?php
// src/member/confirm_profile_handler.php — GET+POST /member/confirm-profile
// First-login GDPR confirmation. Member reviews and saves their player profile,
// then confirmed_at is stamped. Also accessible afterwards to update profile data.

declare(strict_types=1);

require_member();

$pdo     = get_db();
$user_id = (int)$_SESSION['user_id'];
$is_first_confirm = $_SESSION['confirmed_at'] === null;

// Load linked member_id (profile)
$link_stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$link_stmt->execute([$user_id]);
$player_id = (int)($link_stmt->fetchColumn() ?: 0);

$player = null;
$clubs  = [];
$error  = '';

if ($player_id) {
    set_admin_context($pdo);
    $p_stmt = $pdo->prepare(
        "SELECT p.*, c.name AS club_name
         FROM members p LEFT JOIN clubs c ON c.id = p.club_id
         WHERE p.id = ?"
    );
    $p_stmt->execute([$player_id]);
    $player = $p_stmt->fetch();

    $clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], 'member', $user_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($player_id) {
        $first_name   = trim($_POST['first_name']   ?? '');
        $last_name    = trim($_POST['last_name']    ?? '');
        $email_raw    = trim($_POST['email']        ?? '');
        $phone        = trim($_POST['phone']        ?? '');
        $contact_name  = trim($_POST['contact_name']  ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $description   = trim($_POST['description']   ?? '');
        $club_id       = (int)($_POST['club_id']      ?? 0);

        if (empty($first_name) || empty($last_name)) {
            $error = 'Vor- und Nachname sind erforderlich.';
        } elseif ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } elseif ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige Kontakt-E-Mail-Adresse.';
        } else {
            set_admin_context($pdo);
            $pdo->prepare(
                "UPDATE members SET first_name=?, last_name=?, email=?, phone=?,
                  contact_name=?, contact_phone=?, contact_email=?, description=?, club_id=? WHERE id=?"
            )->execute([
                $first_name, $last_name,
                $email_raw !== '' ? $email_raw : null,
                $phone !== '' ? $phone : null,
                $contact_name !== '' ? $contact_name : null,
                $contact_phone !== '' ? $contact_phone : null,
                $contact_email !== '' ? $contact_email : null,
                $description !== '' ? $description : null,
                $club_id > 0 ? $club_id : null,
                $player_id,
            ]);
            reset_rls_context($pdo);
            set_team_context($pdo, (int)$_SESSION['team_id'], 'member', $user_id);
        }
    }

    if (!$error) {
        // Stamp confirmation (idempotent — only sets once)
        $pdo->prepare("UPDATE users SET confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?")
            ->execute([$user_id]);
        $_SESSION['confirmed_at'] = $_SESSION['confirmed_at'] ?? date('c');
        redirect($is_first_confirm ? '/member/lists' : '/member/profile');
    }
}

require ROOT_PATH . '/src/templates/member/layout.php';

render_member_page(
    $is_first_confirm ? 'Profil bestätigen' : 'Profil bearbeiten',
    'profile',
    function() use ($player, $clubs, $error, $is_first_confirm) {
        require ROOT_PATH . '/src/templates/member/confirm_profile.php';
    }
);
