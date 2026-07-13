<?php
// src/admin/coordinator_edit_email_handler.php — GET+POST /admin/coordinators/{id}/edit-email
// Admin-only: set or update a coordinator's email address.
// Per D-06: coordinator email is managed exclusively by admin.

declare(strict_types=1);

require_admin();

$coordinator_id = (int)($_REQUEST['coordinator_id'] ?? 0);
if ($coordinator_id <= 0) {
    redirect('/admin/coordinators');
}

$pdo = get_db();

// Verify coordinator exists (ownership check: must be role=coordinator)
$check = $pdo->prepare(
    "SELECT id, first_name, last_name, email FROM users WHERE id = ? AND role = 'coordinator'"
);
$check->execute([$coordinator_id]);
$coordinator = $check->fetch(PDO::FETCH_ASSOC);

if (!$coordinator) {
    redirect('/admin/coordinators');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email_raw = trim($_POST['email'] ?? '');

    if ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse. Bitte überprüfe deine Eingabe.';
    } elseif ($email_raw !== '' && mb_strlen($email_raw) > 255) {
        $error = 'E-Mail-Adresse zu lang (max. 255 Zeichen).';
    } else {
        $upd = $pdo->prepare(
            "UPDATE users SET email = ? WHERE id = ? AND role = 'coordinator'"
        );
        $upd->execute([$email_raw !== '' ? $email_raw : null, $coordinator_id]);
        redirect('/admin/coordinators?success=' . urlencode(
            'E-Mail-Adresse für ' . $coordinator['first_name'] . ' ' . $coordinator['last_name'] . ' gespeichert.'
        ));
    }
}

if (!empty($_GET['success'])) {
    $success = e($_GET['success']);
}

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page(
    'E-Mail-Adresse bearbeiten — ' . e($coordinator['first_name'] . ' ' . $coordinator['last_name']),
    'coordinators',
    function() use ($coordinator, $error, $success) {
        require ROOT_PATH . '/src/templates/admin/coordinator_edit_email.php';
    }
);
