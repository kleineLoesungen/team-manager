<?php
// src/push/ticker_notify_handler.php — POST /{coordinator|member}/ticker/{id}/notify
// Opt-in / opt-out for push notifications of one ticker (start notice + every new entry).
// The device itself is registered beforehand via /push/subscribe (JavaScript).

declare(strict_types=1);

require_once ROOT_PATH . '/src/push/ticker_push.php';

$role = $_REQUEST['role'] === 'coordinator' ? 'coordinator' : 'member';
$role === 'coordinator' ? require_coordinator() : require_member();
require_csrf();

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);
$user_id   = (int)$_SESSION['user_id'];
$pdo       = get_db();

// Zugriff prüfen: Ticker des eigenen Teams, bei Koordinatoren auch weiterer betreuter Teams
set_admin_context($pdo);
$stmt = $pdo->prepare(
    "SELECT t.id FROM tickers t
     WHERE t.id = ? AND (t.team_id = ?
        OR (? = 'coordinator' AND EXISTS (
            SELECT 1 FROM coordinator_teams ct
            WHERE ct.team_id = t.team_id AND ct.user_id = ? AND ct.left_at IS NULL)))"
);
$stmt->execute([$ticker_id, (int)$_SESSION['team_id'], $role, $user_id]);
if (!$stmt->fetchColumn()) {
    reset_rls_context($pdo);
    http_response_code(404);
    echo '<h1>Ticker nicht gefunden</h1>';
    exit;
}

$on = ($_POST['on'] ?? '') === '1';
push_ticker_set_subscribed($pdo, $ticker_id, $user_id, $on);
reset_rls_context($pdo);

redirect("/{$role}/ticker/{$ticker_id}?success=" . ($on ? 'notify_on' : 'notify_off'));
