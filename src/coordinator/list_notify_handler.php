<?php
// src/coordinator/list_notify_handler.php — GET+POST /coordinator/lists/{id}/notify
// Coordinator review and send notification for a list.
// Per D-01: button on list detail page links here.
// Per D-02: recipients determined by visibility (public/protected → members, private → coordinators).
// Per D-03: review page shows recipient info + mail preview before send.
// Per D-04: POST sends emails → PRG redirect back to list detail with success banner.

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/visibility.php';
require_coordinator();
require_once ROOT_PATH . '/src/utils/email_composer.php';

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();

// Ownership check: coordinator must own this list's team
if (!can_view_list($list_id)) {
    http_response_code(404);
    echo '<h1>Liste nicht gefunden</h1>';
    exit;
}

// Fetch list metadata
$list_stmt = $pdo->prepare("SELECT id, name, visibility, date FROM lists WHERE id = ?");
$list_stmt->execute([$list_id]);
$list = $list_stmt->fetch(PDO::FETCH_ASSOC);

if (!$list) {
    redirect('/coordinator/lists');
}

// Determine recipient group based on visibility (D-02)
$target_role = ($list['visibility'] === 'private') ? 'coordinator' : 'member';

// Preview link for the review page (uses target_role; per-recipient links computed in send loop)
$content_link = app_url($target_role === 'member'
    ? '/member/lists/' . $list_id
    : '/coordinator/lists/' . $list_id);

// Fetch all recipients in target role (active, with or without email)
$rec_stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, email
     FROM users
     WHERE team_id = ? AND role = ? AND is_active = TRUE
     ORDER BY first_name, last_name"
);
$rec_stmt->execute([$_SESSION['team_id'], $target_role]);
$all_recipients = $rec_stmt->fetchAll(PDO::FETCH_ASSOC);

$with_email    = array_values(array_filter($all_recipients, fn($u) => !empty($u['email'])));
$without_email = array_values(array_filter($all_recipients, fn($u) =>  empty($u['email'])));

// Fetch team name for subject prefix
$team_stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
$team_stmt->execute([$_SESSION['team_id']]);
$team_row      = $team_stmt->fetch(PDO::FETCH_ASSOC);
$team_name     = $team_row['name'] ?? 'Team';
$subject_prefilled = '[' . $team_name . '] ' . $list['name'];

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
        // Re-fetch recipients for actual send (never trust GET-time state)
        $re_stmt = $pdo->prepare(
            "SELECT first_name, email, role FROM users
             WHERE team_id = ? AND role = ? AND is_active = TRUE AND email IS NOT NULL"
        );
        $re_stmt->execute([$_SESSION['team_id'], $target_role]);
        $send_recipients = $re_stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent   = 0;
        $failed = 0;

        foreach ($send_recipients as $recipient) {
            $recipient_link = app_url($recipient['role'] === 'member'
                ? '/member/lists/' . $list_id
                : '/coordinator/lists/' . $list_id);
            $body = compose_list_notification_body(
                $recipient['first_name'],
                $body_raw,
                $list['name'],
                $recipient_link
            );
            if (send_notification_email($recipient['email'], $subject_raw, $body)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($sent > 0 && $failed === 0) {
            redirect('/coordinator/lists/' . $list_id . '?notify_success=' .
                urlencode("Benachrichtigung an $sent Empfänger gesendet."));
        } elseif ($sent > 0) {
            redirect('/coordinator/lists/' . $list_id . '?notify_success=' .
                urlencode("Benachrichtigung an $sent gesendet. $failed fehlgeschlagen."));
        } else {
            $error = 'Versand fehlgeschlagen. Bitte versuche es erneut oder wende dich an den Serveradministrator.';
        }
    }
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Benachrichtigung prüfen', 'lists', function() use (
    $list, $with_email, $without_email, $subject_prefilled, $content_link, $error, $success
) {
    if ($error)   echo '<div class="alert alert-danger mb-3">'  . e($error)   . '</div>';
    if ($success) echo '<div class="alert alert-success mb-3">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/list_notify.php';
});
