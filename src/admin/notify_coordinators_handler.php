<?php
// src/admin/notify_coordinators_handler.php — GET+POST /admin/notify
// Admin sends a plain-text notification to all active coordinators with email addresses.
// Per D-05: no list/file reference — free-form message only.
// Per D-06: coordinator emails are admin-managed; coordinators cannot see their own email.

declare(strict_types=1);

require_admin();
require_once ROOT_PATH . '/src/utils/email_composer.php';

$pdo = get_db();

// Fetch all active coordinators (cross-team; no team_id filter for admin)
$stmt = $pdo->query(
    "SELECT id, first_name, last_name, email
     FROM users
     WHERE role = 'coordinator' AND is_active = TRUE
     ORDER BY first_name, last_name"
);
$all_coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);

$with_email    = array_values(array_filter($all_coordinators, fn($c) => !empty($c['email'])));
$without_email = array_values(array_filter($all_coordinators, fn($c) =>  empty($c['email'])));

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $subject_raw = trim($_POST['subject'] ?? '');
    $body_raw    = trim($_POST['body']    ?? '');

    if ($subject_raw === '') {
        $error = 'Bitte gib einen Betreff an.';
    } elseif (mb_strlen($subject_raw) > 200) {
        $error = 'Betreff zu lang (max. 200 Zeichen).';
    } elseif ($body_raw === '') {
        $error = 'Bitte gib eine Nachricht ein.';
    } elseif (mb_strlen($body_raw) > 2000) {
        $error = 'Nachricht zu lang (max. 2000 Zeichen).';
    } else {
        // Re-fetch recipients (never trust GET state for actual send)
        $re_stmt = $pdo->query(
            "SELECT email FROM users
             WHERE role = 'coordinator' AND is_active = TRUE AND email IS NOT NULL"
        );
        $recipients = $re_stmt->fetchAll(PDO::FETCH_COLUMN);

        $sent  = 0;
        $failed = 0;

        $email_body = compose_admin_notification_body($body_raw);

        foreach ($recipients as $to) {
            if (send_notification_email($to, $subject_raw, $email_body)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($sent > 0 && $failed === 0) {
            redirect('/admin/notify?success=' . urlencode("Nachricht an $sent Koordinator(en) gesendet."));
        } elseif ($sent > 0) {
            redirect('/admin/notify?success=' . urlencode("Nachricht an $sent gesendet. $failed fehlgeschlagen."));
        } else {
            $error = 'Versand fehlgeschlagen. Bitte versuche es erneut oder wende dich an den Serveradministrator.';
        }
    }
}

if (!empty($_GET['success'])) {
    $success = e($_GET['success']);
}

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Koordinatoren benachrichtigen', 'notify', function() use ($with_email, $without_email, $error, $success) {
    require ROOT_PATH . '/src/templates/admin/notify_coordinators.php';
});
