<?php
// src/coordinator/profile_handler.php — GET+POST /coordinator/profile and /coordinator/confirm-profile
// Coordinators edit their own contact data and (on first login) confirm their profile per DSGVO.

declare(strict_types=1);

require_coordinator();

$pdo     = get_db();
$user_id = (int)$_SESSION['user_id'];

$is_confirm_route = str_ends_with($_SERVER['REQUEST_URI'] ?? '', 'confirm-profile');
$is_first_confirm = $_SESSION['confirmed_at'] === null;

$stmt = $pdo->prepare(
    "SELECT first_name, last_name, email, phone, confirmed_at FROM users WHERE id = ?"
);
$stmt->execute([$user_id]);
$self = $stmt->fetch();

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
        $pdo->prepare(
            "UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?"
        )->execute([
            $first_name,
            $last_name,
            $email_raw !== '' ? $email_raw : null,
            $phone !== '' ? $phone : null,
            $user_id,
        ]);

        // Update display name in session
        $_SESSION['display_name'] = $first_name . ' ' . $last_name;

        // Stamp confirmation (idempotent — COALESCE keeps original timestamp)
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
