<?php
// src/push/subscribe_handler.php — POST /push/subscribe
// Registers the push subscription of the current device for the signed-in user.
// Called by the "Ticker abonnieren" button right after PushManager.subscribe().

declare(strict_types=1);

require_once ROOT_PATH . '/src/push/ticker_push.php';

($_SESSION['role'] ?? '') === 'coordinator' ? require_coordinator() : require_member();
require_csrf();

$sub      = json_decode((string)($_POST['subscription'] ?? ''), true);
$endpoint = is_array($sub) ? (string)($sub['endpoint'] ?? '') : '';
$p256dh   = is_array($sub) ? (string)($sub['keys']['p256dh'] ?? '') : '';
$auth     = is_array($sub) ? (string)($sub['keys']['auth'] ?? '') : '';

if (strlen($endpoint) > 1000 || !webpush_endpoint_allowed($endpoint)
    || strlen(b64u_decode($p256dh)) !== 65 || strlen(b64u_decode($auth)) !== 16) {
    http_response_code(400);
    exit;
}

$pdo = get_db();
push_save_subscription($pdo, (int)$_SESSION['user_id'], $endpoint, $p256dh, $auth);
reset_rls_context($pdo);
http_response_code(204);
