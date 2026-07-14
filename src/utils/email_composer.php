<?php
// src/utils/email_composer.php — Email sending utility for Phase 5 notifications
// Provides send_notification_email() and plain-text body composition helpers.
// Security: strips \r\n from subject/headers (prevents header injection per RESEARCH pitfall 1).
// Encoding: UTF-8 Content-Type header prevents garbled German umlauts (pitfall 3).
// Driver: MAIL_DRIVER=mail uses PHP mail(); MAIL_DRIVER=smtp uses PHPMailer (bundled in src/lib/phpmailer/).

declare(strict_types=1);

/**
 * Send a plain-text notification email.
 *
 * @param  string $to      Validated recipient email address (must pass filter_var before calling)
 * @param  string $subject Email subject — MUST NOT contain coordinator user input without sanitization
 * @param  string $body    Plain-text email body
 * @return bool            TRUE if sent (or logged in dev), FALSE on failure
 */
function send_notification_email(string $to, string $subject, string $body): bool {
    // Guard: validate recipient address (defense-in-depth; caller should also validate)
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[MAIL] Invalid recipient address: ' . $to);
        return false;
    }

    // Sanitize subject: strip newlines to prevent header injection (pitfall 1)
    $subject_safe = str_replace(["\r", "\n", "\0"], '', $subject);

    // Sanitize body: remove null bytes, normalize to \n (pitfall 2)
    $body_clean = str_replace(["\0", "\r\n", "\r"], ['', "\n", "\n"], trim($body));

    // Development mode: log only, do not send
    if (defined('APP_ENV') && APP_ENV === 'development') {
        error_log('[MAIL DEV] To: ' . $to . ' | Subject: ' . $subject_safe);
        error_log('[MAIL DEV BODY] ' . mb_substr($body_clean, 0, 500));
        return true;
    }

    if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'smtp') {
        return _send_via_phpmailer($to, $subject_safe, $body_clean);
    }

    // PHP mail() fallback — Content-Type header ensures UTF-8 for German umlauts (pitfall 3)
    $from_addr = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@team-manager';
    $from_name = defined('MAIL_FROM_NAME')    ? MAIL_FROM_NAME    : 'Team Manager';

    $headers = implode("\r\n", [
        'From: ' . str_replace(["\r", "\n"], '', $from_name) . ' <' . $from_addr . '>',
        'Reply-To: ' . $from_addr,
        'X-Mailer: Team Manager/1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);

    $result = mail($to, $subject_safe, $body_clean, $headers);
    if (!$result) {
        error_log('[MAIL] mail() returned false for recipient: ' . $to . ' subject: ' . $subject_safe);
    }
    return $result;
}

/**
 * Send via PHPMailer SMTP (bundled in src/lib/phpmailer/ — no Composer required).
 */
function _send_via_phpmailer(string $to, string $subject, string $body): bool {
    require_once __DIR__ . '/../lib/phpmailer/Exception.php';
    require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/../lib/phpmailer/SMTP.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = defined('MAIL_HOST')     ? MAIL_HOST     : '';
        $mail->Port       = defined('MAIL_PORT')     ? MAIL_PORT     : 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
        $mail->Password   = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : ''; // Never logged
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $from_addr = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@team-manager';
        $from_name = defined('MAIL_FROM_NAME')    ? MAIL_FROM_NAME    : 'Team Manager';

        $mail->setFrom($from_addr, $from_name);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        return $mail->send();
    } catch (Exception $e) {
        // Log message only — never log MAIL_PASSWORD
        error_log('[MAIL SMTP] PHPMailer error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Compose plain-text body for a list notification.
 * Subject is entered by coordinator; this builds the body appended with the link.
 */
function compose_list_notification_body(
    string $recipient_first_name,
    string $coordinator_message,
    string $list_name,
    string $content_link
): string {
    $name    = trim($recipient_first_name);
    $message = trim($coordinator_message);
    $title   = trim($list_name);
    $link    = trim($content_link);

    return "Hallo {$name},\n\n{$message}\n\n---\n\n{$title}\nLink: {$link}";
}

/**
 * Compose plain-text body for a file (markdown document) notification.
 */
function compose_file_notification_body(
    string $recipient_first_name,
    string $coordinator_message,
    string $file_name,
    string $content_link
): string {
    $name    = trim($recipient_first_name);
    $message = trim($coordinator_message);
    $title   = trim($file_name);
    $link    = trim($content_link);

    return "Hallo {$name},\n\n{$message}\n\n---\n\n{$title}\nLink: {$link}";
}

/**
 * Compose plain-text body for admin-to-coordinators notification (no content link).
 */
function compose_admin_notification_body(string $message): string {
    return trim($message);
}
