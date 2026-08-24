<?php
// src/coordinator/profile_handler.php — GET+POST /coordinator/profile and /coordinator/confirm-profile
// Coordinators edit their own contact data via their linked player record.

declare(strict_types=1);

require_coordinator();

$pdo     = get_db();
$user_id = (int)$_SESSION['user_id'];

$is_confirm_route = str_ends_with($_SERVER['REQUEST_URI'] ?? '', 'confirm-profile');
$is_first_confirm = $_SESSION['confirmed_at'] === null;

// Load coordinator's member record (member_id is NOT NULL after migration 029)
$stmt = $pdo->prepare(
    "SELECT u.member_id, u.confirmed_at,
            p.first_name, p.last_name, p.email, p.phone
     FROM users u
     JOIN members p ON p.id = u.member_id
     WHERE u.id = ?"
);
$stmt->execute([$user_id]);
$self = $stmt->fetch();
$player_id = (int)$self['member_id'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email_raw  = trim($_POST['email']      ?? '');
    $phone      = trim($_POST['phone']      ?? '');

    if (empty($first_name) || empty($last_name)) {
        $error = 'Vor- und Nachname sind erforderlich.';
    } elseif ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse.';
    } else {
        // Write to members (canonical person table) — needs admin context to bypass RLS
        set_admin_context($pdo);
        $pdo->prepare(
            "UPDATE members SET first_name=?, last_name=?, email=?, phone=? WHERE id=?"
        )->execute([
            $first_name,
            $last_name,
            $email_raw !== '' ? $email_raw : null,
            $phone !== '' ? $phone : null,
            $player_id,
        ]);
        // Sync display_name session key and confirmed_at if needed
        reset_rls_context($pdo);
        set_team_context($pdo, (int)$_SESSION['team_id'], 'coordinator', $user_id);

        $_SESSION['display_name'] = $first_name . ' ' . $last_name;

        if ($is_confirm_route) {
            $pdo->prepare("UPDATE users SET confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?")
                ->execute([$user_id]);
            $_SESSION['confirmed_at'] = $_SESSION['confirmed_at'] ?? date('c');
        }

        redirect($is_confirm_route && $is_first_confirm ? '/coordinator/members' : '/coordinator/profile');
    }

    // On validation error, keep submitted values for re-display
    $self = [
        'first_name'   => $_POST['first_name'] ?? '',
        'last_name'    => $_POST['last_name']  ?? '',
        'email'        => $_POST['email']      ?? '',
        'phone'        => $_POST['phone']      ?? '',
        'confirmed_at' => $self['confirmed_at'] ?? null,
    ];
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page(
    ($is_confirm_route && $is_first_confirm) ? 'Profil bestätigen' : 'Mein Profil',
    'profile',
    function() use ($self, $error, $is_confirm_route, $is_first_confirm) {
        require ROOT_PATH . '/src/templates/coordinator/profile.php';
    }
);
