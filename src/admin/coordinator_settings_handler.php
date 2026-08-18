<?php
// src/admin/coordinator_settings_handler.php — GET /admin/coordinators/{id}/settings
// Admin-only: manage coordinator team assignments and club relation.

declare(strict_types=1);

require_admin();

$coordinator_id = (int)($_REQUEST['coordinator_id'] ?? 0);
if ($coordinator_id <= 0) {
    redirect('/admin/coordinators');
}

$pdo = get_db();

$check = $pdo->prepare(
    "SELECT u.id, u.player_id, u.username, p.first_name, p.last_name, p.email, p.phone, p.club_id, cl.name AS club_name
     FROM users u
     JOIN players p ON p.id = u.player_id
     LEFT JOIN clubs cl ON cl.id = p.club_id
     WHERE u.id = ? AND u.role = 'coordinator'"
);
$check->execute([$coordinator_id]);
$coordinator = $check->fetch(PDO::FETCH_ASSOC);

if (!$coordinator) {
    redirect('/admin/coordinators');
}

// Handle personal data update (POST to this page)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email_raw  = trim($_POST['email']      ?? '');
    $phone_raw  = trim($_POST['phone']      ?? '');

    if (empty($first_name) || empty($last_name)) {
        $error = 'Vor- und Nachname sind erforderlich.';
    } elseif ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse.';
    } elseif ($email_raw !== '' && mb_strlen($email_raw) > 255) {
        $error = 'E-Mail-Adresse zu lang (max. 255 Zeichen).';
    } elseif ($phone_raw !== '' && mb_strlen($phone_raw) > 50) {
        $error = 'Telefonnummer zu lang (max. 50 Zeichen).';
    } else {
        $email_val = $email_raw !== '' ? $email_raw : null;
        $phone_val = $phone_raw !== '' ? $phone_raw : null;

        $pdo->prepare("UPDATE players SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?")
            ->execute([$first_name, $last_name, $email_val, $phone_val, (int)$coordinator['player_id']]);

        redirect('/admin/coordinators/' . $coordinator_id . '/settings?success=' . urlencode('Daten gespeichert.'));
    }
}

$ct_stmt = $pdo->prepare(
    "SELECT ct.team_id, t.name AS team_name
     FROM coordinator_teams ct
     JOIN teams t ON t.id = ct.team_id
     WHERE ct.user_id = ? AND ct.left_at IS NULL
     ORDER BY ct.joined_at ASC"
);
$ct_stmt->execute([$coordinator_id]);
$assigned_teams = $ct_stmt->fetchAll();
$assigned_ids   = array_column($assigned_teams, 'team_id');

$all_teams       = $pdo->query("SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY sort_order ASC, name ASC")->fetchAll();
$available_teams = array_filter($all_teams, fn($t) => !in_array($t['id'], $assigned_ids));

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? e($_GET['success']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page(
    'Einstellungen — ' . e($coordinator['first_name'] . ' ' . $coordinator['last_name']),
    'coordinators',
    function() use ($coordinator, $assigned_teams, $available_teams, $clubs, $error, $success) {
        require ROOT_PATH . '/src/templates/admin/coordinator_settings.php';
    }
);
