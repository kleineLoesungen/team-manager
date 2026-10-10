<?php
// src/push/unsubscribe_handler.php — POST /push/unsubscribe
// Removes this device's push subscription (profile: "Push ausschalten"). Only devices of the
// signed-in person (any of their accounts) or this browser's own device (cookie, guests) can be
// removed. Afterwards the device gets no push at all — neither from coordinators nor from tickers.

declare(strict_types=1);

require_once ROOT_PATH . '/src/push/ticker_push.php';

$role = $_SESSION['role'] ?? '';
$signed_in = !empty($_SESSION['user_id']) && in_array($role, ['coordinator', 'member'], true);
if ($signed_in) {
    $role === 'coordinator' ? require_coordinator() : require_member();
}
require_csrf();

$endpoint = (string)($_POST['endpoint'] ?? '');
if ($endpoint === '' || strlen($endpoint) > 1000) {
    http_response_code(400);
    exit;
}

$pdo     = get_db();
$user_id = (int)($_SESSION['user_id'] ?? 0);
$device  = push_device_id($pdo);   // dieses Gerät per Cookie (auch Gäste)
as_admin($pdo, function () use ($pdo, $endpoint, $user_id, $device) {
    $pdo->prepare(
        "DELETE FROM push_subscriptions
         WHERE endpoint = ?
           AND (id = ?
                OR user_id IN (SELECT d.id FROM users u JOIN users d ON d.member_id = u.member_id
                               WHERE u.id = ? AND u.member_id IS NOT NULL
                               UNION SELECT ?))"
    )->execute([$endpoint, $device ?? 0, $user_id, $user_id]);
});
http_response_code(204);
