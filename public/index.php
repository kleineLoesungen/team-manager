<?php
// public/index.php — Front controller / router
// All HTTP requests are routed through here.

declare(strict_types=1);

$_parent = dirname(__DIR__);
if (is_dir($_parent . '/src')) {
    define('ROOT_PATH', $_parent);
} else {
    define('ROOT_PATH', $_parent . '/apps/team-manager');
}
unset($_parent);

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

    // Match /admin/teams/{id}/edit, /admin/teams/{id}/deactivate, /admin/teams/{id}/reactivate
    (bool)preg_match('#^/admin/teams/(\d+)/(edit|deactivate|reactivate)$#', $path, $matches)
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

    $path === '/admin/settings'
        => require ROOT_PATH . '/src/admin/settings_handler.php',

    (bool)preg_match('#^/admin/coaches/(\d+)/(deactivate|reactivate|reset-password)$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['coach_id'] = (int)$matches[1];
            $_REQUEST['action']   = $matches[2];
            require ROOT_PATH . '/src/admin/coach_action_handler.php';
        })(),

    // ── Coach: Players ─────────────────────────────────────────────────
    $path === '/coach' || $path === '/coach/players'
        => require ROOT_PATH . '/src/coach/players_handler.php',

    $path === '/coach/players/create'
        => require ROOT_PATH . '/src/coach/player_create_handler.php',

    (bool)preg_match('#^/coach/players/(\d+)/(deactivate|reactivate|reset-password)$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['player_id'] = (int)$matches[1];
            $_REQUEST['action']    = $matches[2];
            require ROOT_PATH . '/src/coach/player_action_handler.php';
        })(),

    // ── Coach: Lists ───────────────────────────────────────────────────
    $path === '/coach/lists'
        => require ROOT_PATH . '/src/coach/lists_handler.php',

    $path === '/coach/lists/create'
        => require ROOT_PATH . '/src/coach/list_create_handler.php',

    // /coach/lists/{id}/settings — GET: show settings form; POST: change visibility (LIST-05)
    (bool)preg_match('#^/coach/lists/(\d+)/settings$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/coach/list_settings_handler.php';
        })(),

    // /coach/lists/{id}/columns/create — POST: add local column to list (LIST-03)
    (bool)preg_match('#^/coach/lists/(\d+)/columns/create$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/coach/list_column_create_handler.php';
        })(),

    // /coach/lists/{id}/rows/{player_id}/edit — GET/POST: edit player row (CELL-02)
    (bool)preg_match('#^/coach/lists/(\d+)/rows/(\d+)/edit$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id']   = (int)$matches[1];
            $_REQUEST['player_id'] = (int)$matches[2];
            require ROOT_PATH . '/src/coach/list_row_edit_handler.php';
        })(),

    // /coach/lists/{id} — GET: list detail table (must come AFTER more specific routes)
    (bool)preg_match('#^/coach/lists/(\d+)$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/coach/list_detail_handler.php';
        })(),

    // ── Coach: Columns (global) ────────────────────────────────────────
    $path === '/coach/columns'
        => require ROOT_PATH . '/src/coach/columns_handler.php',

    $path === '/coach/columns/create'
        => require ROOT_PATH . '/src/coach/columns_create_handler.php',

    // ── Coach: Statistics ──────────────────────────────────────────────
    $path === '/coach/stats'
        => require ROOT_PATH . '/src/coach/stats_handler.php',

    // ── Player: Lists ─────────────────────────────────────────────────
    $path === '/player' || $path === '/player/lists'
        => require ROOT_PATH . '/src/player/lists_handler.php',

    // /player/lists/{id}/rows/{player_id}/edit — GET/POST: edit own row (CELL-01)
    (bool)preg_match('#^/player/lists/(\d+)/rows/(\d+)/edit$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id']   = (int)$matches[1];
            $_REQUEST['player_id'] = (int)$matches[2];
            require ROOT_PATH . '/src/player/list_row_edit_handler.php';
        })(),

    // /player/lists/{id} — GET: list detail (must come AFTER more specific routes)
    (bool)preg_match('#^/player/lists/(\d+)$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/player/list_detail_handler.php';
        })(),

    // ── Player: Statistics ────────────────────────────────────────────
    $path === '/player/stats'
        => require ROOT_PATH . '/src/player/stats_handler.php',

    // ── 404 ────────────────────────────────────────────────────────────
    default => (function() {
        http_response_code(404);
        echo '<h1>404 — Seite nicht gefunden</h1>';
    })(),

};
