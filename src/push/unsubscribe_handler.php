<?php
// src/push/unsubscribe_handler.php — POST /push/unsubscribe
// Removes this device's push subscription (profile: "Push ausschalten"). Only devices
// of the signed-in person (any of their accounts) can be removed. Afterwards the device gets no
// push at all — neither from coordinators nor from tickers.

declare(strict_types=1);

($_SESSION['role'] ?? '') === 'coordinator' ? require_coordinator() : require_member();
require_csrf();

$endpoint = (string)($_POST['endpoint'] ?? '');
if ($endpoint === '' || strlen($endpoint) > 1000) {
    http_response_code(400);
    exit;
}

$pdo = get_db();
$user_id = (int)$_SESSION['user_id'];
as_admin($pdo, function () use ($pdo, $endpoint, $user_id) {
    $pdo->prepare(
        "DELETE FROM push_subscriptions
         WHERE endpoint = ?
           AND user_id IN (SELECT d.id FROM users u JOIN users d ON d.member_id = u.member_id
                           WHERE u.id = ? AND u.member_id IS NOT NULL
                           UNION SELECT ?)"
    )->execute([$endpoint, $user_id, $user_id]);
});
http_response_code(204);
