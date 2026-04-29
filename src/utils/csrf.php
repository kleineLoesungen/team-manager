<?php
// src/utils/csrf.php — Session-based CSRF token helpers

/**
 * Generate or return the current session CSRF token.
 * Requires session_start() to have been called first.
 */
function get_csrf_token(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the submitted CSRF token using timing-safe comparison.
 * Returns false if session token missing or tokens do not match.
 */
function validate_csrf_token(string $submitted_token): bool {
    if (empty($submitted_token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted_token);
}

/**
 * Render a hidden CSRF input field for forms.
 */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Abort with 403 if CSRF token is missing or invalid.
 * Call at the top of every POST handler.
 */
function require_csrf(): void {
    if (!validate_csrf_token($_POST['_csrf'] ?? '')) {
        http_response_code(403);
        die('CSRF-Token ungültig.');
    }
}
