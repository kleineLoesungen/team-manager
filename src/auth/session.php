<?php
// src/auth/session.php — Session bootstrap and auth middleware

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__) . '/utils/helpers.php';

/**
 * Start a secure session with OWASP-compliant cookie settings.
 * Must be called before any output. Call once per request at bootstrap.
 * Per D-07: cookie_secure, cookie_httponly, cookie_samesite=Strict, use_strict_mode.
 */
function start_secure_session(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return; // Already started
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    ini_set('session.use_strict_mode',   '1');
    ini_set('session.use_cookies',       '1');
    ini_set('session.use_only_cookies',  '1');
    ini_set('session.gc_maxlifetime',    (string)SESSION_TIMEOUT);
    ini_set('session.cookie_lifetime',   (string)SESSION_TIMEOUT);
    ini_set('session.sid_length',        '256');
    ini_set('session.sid_bits_per_character', '6');

    session_start();
}

/**
 * Sliding window timeout check.
 * Per D-05: max inactivity = SESSION_TIMEOUT (8h). Every request extends window.
 * Per D-06: on timeout, redirect to /login (no JS modal).
 */
function check_session_timeout(): void {
    $now = time();

    if (!isset($_SESSION['last_activity'])) {
        // Fresh session — initialize timestamp
        $_SESSION['last_activity'] = $now;
        return;
    }

    $inactive = $now - $_SESSION['last_activity'];
    if ($inactive > SESSION_TIMEOUT) {
        session_destroy();
        redirect('/login?message=' . urlencode('Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.'));
    }

    // Extend window (sliding)
    $_SESSION['last_activity'] = $now;
}

/**
 * Require a logged-in user (any role: coach or player).
 * Redirects to /login if no valid session.
 */
function require_auth(): void {
    check_session_timeout();
    if (empty($_SESSION['user_id']) && empty($_SESSION['is_admin'])) {
        redirect('/login');
    }
}

/**
 * Require an admin session.
 * Per D-08: every admin page checks $_SESSION['is_admin'].
 * Redirects to /login if not admin.
 */
function require_admin(): void {
    check_session_timeout();
    if (empty($_SESSION['is_admin'])) {
        redirect('/login');
    }
}

/**
 * Check if the current request is authenticated as admin.
 */
function is_admin(): bool {
    return !empty($_SESSION['is_admin']) && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Check if the current request is authenticated as coach or player.
 */
function is_authenticated(): bool {
    return !empty($_SESSION['user_id']);
}
