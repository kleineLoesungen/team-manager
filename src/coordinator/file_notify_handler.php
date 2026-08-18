<?php
// src/coordinator/file_notify_handler.php — GET+POST /coordinator/files/{id}/notify
// Coordinator review and send notification for a file (markdown document).
// Per D-01: button on file detail page links here.
// Per D-02, D-03, D-04: same rules as list_notify but for files table.

declare(strict_types=1);

require_coordinator();
require_once ROOT_PATH . '/src/utils/email_composer.php';

$file_id = (int)($_REQUEST['file_id'] ?? 0);
$pdo     = get_db();

// Fetch file + ownership check
$file_stmt = $pdo->prepare("SELECT id, name, visibility FROM files WHERE id = ? AND team_id = ?");
$file_stmt->execute([$file_id, $_SESSION['team_id']]);
$file = $file_stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    redirect('/coordinator/lists');
}

// Determine recipient role + preview link based on visibility (D-02)
$target_role  = ($file['visibility'] === 'private') ? 'coordinator' : 'member';
$content_link = app_url($target_role === 'member'
    ? '/member/files/' . $file_id
    : '/coordinator/files/' . $file_id);

// Fetch all recipients in target role (active users in an active team, with or without email)
// For member recipients also join linked player to get contact_email
if ($target_role === 'member') {
    // For members: canonical email is players.email (all users are linked after migration 024)
    $rec_stmt = $pdo->prepare(
        "SELECT u.id, p.first_name, p.last_name,
                p.email,
                p.contact_email
         FROM users u JOIN teams t ON t.id = u.team_id
         JOIN players p ON p.id = u.player_id
         WHERE u.team_id = ? AND u.role = 'member' AND u.is_active = TRUE AND t.is_active = TRUE
         ORDER BY p.first_name, p.last_name"
    );
} else {
    $rec_stmt = $pdo->prepare(
        "SELECT u.id, p.first_name, p.last_name, p.email, NULL AS contact_email
         FROM users u
         JOIN teams t ON t.id = u.team_id
         JOIN players p ON p.id = u.player_id
         WHERE u.team_id = ? AND u.role = 'coordinator' AND u.is_active = TRUE AND t.is_active = TRUE
         ORDER BY p.first_name, p.last_name"
    );
}
$rec_stmt->execute([$_SESSION['team_id']]);
$all_recipients = $rec_stmt->fetchAll(PDO::FETCH_ASSOC);

$with_email    = array_values(array_filter($all_recipients,
    fn($u) => !empty($u['email']) || !empty($u['contact_email'])));
$without_email = array_values(array_filter($all_recipients,
    fn($u) =>  empty($u['email']) && empty($u['contact_email'])));

// Team name for subject prefix
$team_stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
$team_stmt->execute([$_SESSION['team_id']]);
$team_row          = $team_stmt->fetch(PDO::FETCH_ASSOC);
$team_name         = $team_row['name'] ?? 'Team';
$subject_prefilled = '[' . $team_name . '] ' . $file['name'];

$error = '';

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
        // Re-fetch recipients for actual send (never trust GET-time state)
        if ($target_role === 'member') {
            $re_stmt = $pdo->prepare(
                "SELECT p.first_name, u.role,
                        p.email,
                        p.contact_email
                 FROM users u JOIN teams t ON t.id = u.team_id
                 JOIN players p ON p.id = u.player_id
                 WHERE u.team_id = ? AND u.role = 'member' AND u.is_active = TRUE AND t.is_active = TRUE
                   AND (p.email IS NOT NULL OR p.contact_email IS NOT NULL)"
            );
        } else {
            $re_stmt = $pdo->prepare(
                "SELECT p.first_name, u.role, p.email, NULL AS contact_email
                 FROM users u
                 JOIN teams t ON t.id = u.team_id
                 JOIN players p ON p.id = u.player_id
                 WHERE u.team_id = ? AND u.role = 'coordinator' AND u.is_active = TRUE AND t.is_active = TRUE
                   AND p.email IS NOT NULL"
            );
        }
        $re_stmt->execute([$_SESSION['team_id']]);
        $send_recipients = $re_stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent   = 0;
        $failed = 0;

        foreach ($send_recipients as $recipient) {
            $recipient_link = app_url($recipient['role'] === 'member'
                ? '/member/files/' . $file_id
                : '/coordinator/files/' . $file_id);
            $body = compose_file_notification_body(
                $recipient['first_name'],
                $body_raw,
                $file['name'],
                $recipient_link
            );
            // Send to member email and/or contact email per the combination rule
            $addresses = array_unique(array_filter([
                $recipient['email']         ?? null,
                $recipient['contact_email'] ?? null,
            ]));
            foreach ($addresses as $addr) {
                if (send_notification_email($addr, $subject_raw, $body)) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
        }

        if ($sent > 0 && $failed === 0) {
            redirect('/coordinator/files/' . $file_id . '?notify_success=' .
                urlencode("Benachrichtigung an $sent Empfänger gesendet."));
        } elseif ($sent > 0) {
            redirect('/coordinator/files/' . $file_id . '?notify_success=' .
                urlencode("Benachrichtigung an $sent gesendet. $failed fehlgeschlagen."));
        } else {
            $error = 'Versand fehlgeschlagen. Bitte versuche es erneut oder wende dich an den Serveradministrator.';
        }
    }
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Benachrichtigung prüfen', 'lists', function() use (
    $file, $with_email, $without_email, $subject_prefilled, $content_link, $error
) {
    if ($error) echo '<div class="alert alert-danger mb-3">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/file_notify.php';
});
