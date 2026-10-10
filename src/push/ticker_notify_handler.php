<?php
// src/push/ticker_notify_handler.php — POST /{coordinator|member}/ticker/{id}/notify and
// POST /ticker/{id}/notify (guests). Opt-in / opt-out for push of one ticker on THIS device
// (start notice + every new entry). The device is registered beforehand via /push/subscribe
// (JavaScript), which also sets the cookie that identifies it.

declare(strict_types=1);

require_once ROOT_PATH . '/src/push/ticker_push.php';

$role = match ($_REQUEST['role'] ?? '') { 'coordinator' => 'coordinator', 'member' => 'member', default => 'guest' };
if ($role === 'coordinator') require_coordinator();
elseif ($role === 'member') require_member();
require_csrf();

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);
$user_id   = (int)($_SESSION['user_id'] ?? 0);
$pdo       = get_db();

// Zugriff prüfen: Gäste jeden Ticker eines aktiven Teams (wie die öffentliche Seite);
// angemeldet der eigene Team-Ticker, Koordinatoren auch der weiterer betreuter Teams
$found = as_admin($pdo, function () use ($pdo, $ticker_id, $role, $user_id) {
    $stmt = $pdo->prepare(
        "SELECT t.id FROM tickers t JOIN teams tm ON tm.id = t.team_id AND tm.is_active = TRUE
         WHERE t.id = ? AND (? = 'guest' OR t.team_id = ?
            OR (? = 'coordinator' AND EXISTS (
                SELECT 1 FROM coordinator_teams ct
                WHERE ct.team_id = t.team_id AND ct.user_id = ? AND ct.left_at IS NULL)))"
    );
    $stmt->execute([$ticker_id, $role, (int)($_SESSION['team_id'] ?? 0), $role, $user_id]);
    return $stmt->fetchColumn();
});
if (!$found) {
    http_response_code(404);
    echo '<h1>Ticker nicht gefunden</h1>';
    exit;
}

$on = ($_POST['on'] ?? '') === '1';
$ok = push_ticker_set_subscribed($pdo, $ticker_id, $on);   // dieses Gerät (Cookie tm_device)

$back = $role === 'guest' ? "/ticker/{$ticker_id}" : "/{$role}/ticker/{$ticker_id}";
redirect($back . '?success=' . ($ok ? ($on ? 'notify_on' : 'notify_off') : 'notify_device'));
