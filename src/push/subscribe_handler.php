<?php
// src/push/subscribe_handler.php — POST /push/subscribe
// Registers the push subscription of the current device — for the signed-in account or, without
// sign-in, as guest device. Sets the cookie tm_device that identifies the device later.
// Called by the layout script right after PushManager.subscribe().

declare(strict_types=1);

require_once ROOT_PATH . '/src/push/ticker_push.php';

// Angemeldet: Gerät gehört dem Konto. Ohne Anmeldung (Gast, Issue #15): Gerät ohne Konto.
$role = $_SESSION['role'] ?? '';
$signed_in = !empty($_SESSION['user_id']) && in_array($role, ['coordinator', 'member'], true);
if ($signed_in) {
    $role === 'coordinator' ? require_coordinator() : require_member();
}
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
push_save_subscription($pdo, $signed_in ? (int)$_SESSION['user_id'] : null, $endpoint, $p256dh, $auth);
reset_rls_context($pdo);
http_response_code(204);
