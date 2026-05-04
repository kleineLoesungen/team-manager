<?php
// public/index.php — Front controller / router
// All HTTP requests are routed through here.

declare(strict_types=1);

// Dev: ROOT_PATH is parent of public/ (src/ lives there)
// Hetzner shared hosting: ROOT_PATH is the webroot itself (src/ deployed alongside index.php)
$_parent = dirname(__DIR__);
define('ROOT_PATH', is_dir($_parent . '/src') ? $_parent : __DIR__);
unset($_parent);

require_once ROOT_PATH . '/config.php';

// TEMP DIAGNOSTIC — remove after Hetzner init issue is resolved
set_exception_handler(function(Throwable $e): void {
    http_response_code(500);
    echo '<pre style="font-family:monospace;padding:1em;white-space:pre-wrap">';
    echo htmlspecialchars($e::class . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString());
    echo '</pre>';
});

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

    // ── Coach: Members ─────────────────────────────────────────────────
    $path === '/moderator' || $path === '/moderator/members'
        => require ROOT_PATH . '/src/coach/players_handler.php',

    $path === '/moderator/members/create'
        => require ROOT_PATH . '/src/coach/player_create_handler.php',

    (bool)preg_match('#^/moderator/members/(\d+)/(deactivate|reactivate|reset-password)$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['player_id'] = (int)$matches[1];
            $_REQUEST['action']    = $matches[2];
            require ROOT_PATH . '/src/coach/player_action_handler.php';
        })(),

    // ── Coach: Lists ───────────────────────────────────────────────────
    $path === '/moderator/lists'
        => require ROOT_PATH . '/src/coach/lists_handler.php',

    $path === '/moderator/lists/create'
        => require ROOT_PATH . '/src/coach/list_create_handler.php',

    // /moderator/lists/{id}/settings — GET: show settings form; POST: change visibility (LIST-05)
    (bool)preg_match('#^/moderator/lists/(\d+)/settings$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/coach/list_settings_handler.php';
        })(),

    // /moderator/lists/{id}/delete — POST: two-step list deletion (LIST-DELETE)
    (bool)preg_match('#^/moderator/lists/(\d+)/delete$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/coach/list_delete_handler.php';
        })(),

    // /moderator/lists/{id}/columns/create — POST: add local column to list (LIST-03)
    (bool)preg_match('#^/moderator/lists/(\d+)/columns/create$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/coach/list_column_create_handler.php';
        })(),

    // /moderator/lists/{id}/rows/{player_id}/edit — GET/POST: edit player row (CELL-02)
    (bool)preg_match('#^/moderator/lists/(\d+)/rows/(\d+)/edit$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id']   = (int)$matches[1];
            $_REQUEST['player_id'] = (int)$matches[2];
            require ROOT_PATH . '/src/coach/list_row_edit_handler.php';
        })(),

    // /moderator/lists/{id} — GET: list detail table (must come AFTER more specific routes)
    (bool)preg_match('#^/moderator/lists/(\d+)$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/coach/list_detail_handler.php';
        })(),

    // ── Coach: Columns (global) ────────────────────────────────────────
    $path === '/moderator/columns'
        => require ROOT_PATH . '/src/coach/columns_handler.php',

    $path === '/moderator/columns/create'
        => require ROOT_PATH . '/src/coach/columns_create_handler.php',

    // ── Coach: Statistics ──────────────────────────────────────────────
    $path === '/moderator/stats'
        => require ROOT_PATH . '/src/coach/stats_handler.php',

    // ── Member: Lists ─────────────────────────────────────────────────
    $path === '/member' || $path === '/member/lists'
        => require ROOT_PATH . '/src/player/lists_handler.php',

    // /member/lists/{id}/rows/{player_id}/edit — GET/POST: edit own row (CELL-01)
    (bool)preg_match('#^/member/lists/(\d+)/rows/(\d+)/edit$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id']   = (int)$matches[1];
            $_REQUEST['player_id'] = (int)$matches[2];
            require ROOT_PATH . '/src/player/list_row_edit_handler.php';
        })(),

    // /member/lists/{id} — GET: list detail (must come AFTER more specific routes)
    (bool)preg_match('#^/member/lists/(\d+)$#', $path, $matches)
        => (function() use ($matches) {
            $_REQUEST['list_id'] = (int)$matches[1];
            require ROOT_PATH . '/src/player/list_detail_handler.php';
        })(),

    // ── Member: Statistics ────────────────────────────────────────────
    $path === '/member/stats'
        => require ROOT_PATH . '/src/player/stats_handler.php',

    // ── 404 ────────────────────────────────────────────────────────────
    default => (function() {
        http_response_code(404);
        echo '<h1>404 — Seite nicht gefunden</h1>';
    })(),

};
