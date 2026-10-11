<?php
// src/auth/session.php — Session bootstrap and auth middleware

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/db/connection.php';

// Sitzungsdauer (Issue #17), gleitend: jede Nutzung verlängert Sitzung und Cookie.
// SESSION_TIMEOUT (config.php, 8 Std.) gilt weiter für den Admin; Koordinatoren und Mitglieder
// bleiben angemeldet, solange sie die App mindestens alle 30 Tage nutzen. Beides lässt sich in
// der config.php der Instanz überschreiben (SESSION_MEMBER_TIMEOUT, SESSION_SAVE_PATH).
if (!defined('SESSION_MEMBER_TIMEOUT')) define('SESSION_MEMBER_TIMEOUT', 30 * 24 * 60 * 60);
// Eigenes Verzeichnis: Im gemeinsamen Temp-Verzeichnis des Hosters räumen fremde Programme mit
// eigenen, oft viel kürzeren Fristen auf — dabei verschwanden auch unsere Sitzungen.
if (!defined('SESSION_SAVE_PATH')) define('SESSION_SAVE_PATH', dirname(__DIR__, 2) . '/sessions');

/** Maximum inactivity for the signed-in role: admin SESSION_TIMEOUT, else SESSION_MEMBER_TIMEOUT. */
function session_timeout(): int {
    return (($_SESSION['role'] ?? '') === 'admin' || !empty($_SESSION['is_admin']))
        ? (int)SESSION_TIMEOUT : (int)SESSION_MEMBER_TIMEOUT;
}

/** Use the app's own session directory (created on first use, blocked for web access). */
function session_use_own_dir(): void {
    $dir = SESSION_SAVE_PATH;
    if ($dir === '') return;   // leer = Standard des Servers
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        error_log('team-manager: session dir ' . $dir . ' not creatable, using server default');
        return;
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!is_writable($dir)) {
        error_log('team-manager: session dir ' . $dir . ' not writable, using server default');
        return;
    }
    ini_set('session.save_path', $dir);
    // Aufräumen übernimmt PHP selbst (in diesem Verzeichnis räumt sonst niemand auf)
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor',     '1000');
}

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

    $lifetime = max((int)SESSION_TIMEOUT, (int)SESSION_MEMBER_TIMEOUT);   // Rolle steht hier noch nicht fest
    session_use_own_dir();
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    ini_set('session.use_strict_mode',   '1');
    ini_set('session.use_cookies',       '1');
    ini_set('session.use_only_cookies',  '1');
    ini_set('session.gc_maxlifetime',    (string)$lifetime);
    ini_set('session.cookie_lifetime',   (string)$lifetime);
    ini_set('session.sid_length',        '64');   // 64 × 6 bits = 384 bits entropy; fits in 255-char filename limit
    ini_set('session.sid_bits_per_character', '6');

    session_start();
}

/**
 * Sliding window timeout check.
 * Max inactivity = session_timeout() (admin 8h, coordinators/members 30 days). Every request
 * extends the window — and the cookie, which PHP itself only sets when the session starts.
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
    if ($inactive > session_timeout()) {
        $current_uri = $_SERVER['REQUEST_URI'] ?? '';
        session_destroy();
        redirect('/login?message=' . urlencode('Deine Sitzung ist abgelaufen. Bitte meld dich erneut an.')
            . ($current_uri !== '' ? '&return_to=' . urlencode($current_uri) : ''));
    }

    // Extend window (sliding)
    $_SESSION['last_activity'] = $now;

    // Cookie mitziehen (höchstens stündlich), sonst läuft es trotz Nutzung nach der Anmeldung ab
    if ($now - (int)($_SESSION['cookie_refreshed'] ?? 0) > 3600 && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['cookie_refreshed'] = $now;
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => $now + session_timeout(),
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}

/**
 * Require a logged-in user (any role: coach or member).
 * Redirects to /login if no valid session.
 */
function require_auth(): void {
    check_session_timeout();
    if (empty($_SESSION['user_id']) && empty($_SESSION['is_admin'])) {
        redirect('/login?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
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
        redirect('/login?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    set_admin_context(get_db());
}

/**
 * Require a coordinator session. Per D-04:
 * Checks $_SESSION['role'] === 'coordinator', sets RLS team context, redirects on failure.
 */
function require_coordinator(): void {
    // Guard: multi-team coordinator must complete team selection before accessing coordinator pages
    if (!empty($_SESSION['pending_team_pick'])) {
        redirect('/coordinator/select-team');
    }
    check_session_timeout();
    if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'member') {
        require_once ROOT_PATH . '/src/auth/role_redirect.php';
        role_mismatch('coordinator');   // weiterleiten oder "Kein Zugriff" — nie zum Login (Schleife)
    }
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'coordinator') {
        redirect('/login?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    $pdo = get_db();
    reset_rls_context($pdo); // Clear any stale admin context from a prior request on this connection
    set_team_context($pdo, (int)$_SESSION['team_id'], 'coordinator', (int)$_SESSION['user_id']);
}

/**
 * Require a member session.
 * Checks $_SESSION['role'] === 'member', sets RLS team context with role + user_id, redirects on failure.
 */
function require_member(): void {
    check_session_timeout();
    if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'coordinator' && empty($_SESSION['pending_team_pick'])) {
        require_once ROOT_PATH . '/src/auth/role_redirect.php';
        role_mismatch('member');
    }
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'member') {
        redirect('/login?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    $pdo = get_db();
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], 'member', (int)$_SESSION['user_id']);
}

/**
 * Check if the current request is authenticated as admin.
 */
function is_admin(): bool {
    return !empty($_SESSION['is_admin']) && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Check if the current request is authenticated as coach or member.
 */
function is_authenticated(): bool {
    return !empty($_SESSION['user_id']);
}
