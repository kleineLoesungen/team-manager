<?php
// public/index.php — Front controller / router
// All HTTP requests are routed through here.

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config.php';
require_once ROOT_PATH . '/src/auth/session.php';
require_once ROOT_PATH . '/src/db/connection.php';
require_once ROOT_PATH . '/src/utils/csrf.php';
require_once ROOT_PATH . '/src/utils/helpers.php';

// Start secure session on every request
start_secure_session();

// Parse the request path (strip query string, normalize trailing slash)
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path   = rtrim($path, '/') ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Route dispatch
match (true) {

    // ── Login ──────────────────────────────────────────────────────────
    $path === '/' || $path === '/login'
        => require ROOT_PATH . '/src/auth/login_handler.php',

    $path === '/logout'
        => require ROOT_PATH . '/src/auth/logout_handler.php',

    // ── Admin: Teams ───────────────────────────────────────────────────
    $path === '/admin' || $path === '/admin/teams'
        => require ROOT_PATH . '/src/admin/teams_handler.php',

    $path === '/admin/teams/create'
        => require ROOT_PATH . '/src/admin/team_create_handler.php',

    // Match /admin/teams/{id}/edit and /admin/teams/{id}/deactivate
    (bool)preg_match('#^/admin/teams/(\d+)/(edit|deactivate|reset-password)$#', $path, $matches)
        => (function() use ($matches, $method) {
            $team_id = (int)$matches[1];
            $action  = $matches[2];
            $_REQUEST['team_id'] = $team_id;
            $_REQUEST['action']  = $action;
            require ROOT_PATH . '/src/admin/team_action_handler.php';
        })(),

    // ── Admin: Coaches ─────────────────────────────────────────────────
    $path === '/admin/coaches'
        => require ROOT_PATH . '/src/admin/coaches_handler.php',

    $path === '/admin/coaches/create'
        => require ROOT_PATH . '/src/admin/coach_create_handler.php',

    (bool)preg_match('#^/admin/coaches/(\d+)/(edit|deactivate|reset-password)$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['coach_id'] = (int)$matches[1];
            $_REQUEST['action']   = $matches[2];
            require ROOT_PATH . '/src/admin/coach_action_handler.php';
        })(),

    // ── 404 ────────────────────────────────────────────────────────────
    default => (function() {
        http_response_code(404);
        echo '<h1>404 — Seite nicht gefunden</h1>';
    })(),

};
