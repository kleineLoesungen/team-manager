<?php
// src/push/ticker_push.php — Push-Benachrichtigungen für Live-Ticker
//
// Opt-in pro Ticker und GERÄT (ticker_subscriptions → push_subscriptions), für angemeldete
// Mitglieder und Koordinatoren ebenso wie für Gäste ohne Konto (Issue #15). Das Gerät erkennt der
// Server am Cookie tm_device (push_subscriptions.device_token), gesetzt bei /push/subscribe.
// Abonnenten bekommen
//   - die Startmeldung zur Startzeit (event_date + start_time, deutsche Ortszeit). Ohne Cronjob:
//     geprüft bei Seitenaufrufen, höchstens einmal pro Minute (push_check_due_starts). Kommt
//     niemand vorbei oder hat der Ticker keine Startzeit, löst der erste Eintrag sie aus.
//   - jeden neuen Eintrag, außer dem eigenen.
// Verschickt wird nach dem Senden der HTTP-Antwort (push_defer), damit Posten nicht wartet.

declare(strict_types=1);

require_once ROOT_PATH . '/src/lib/webpush.php';

const PUSH_START_GRACE_HOURS = 3;   // ältere Startzeiten lösen keine Startmeldung mehr aus

/**
 * VAPID key pair of this installation. Generated on first use and stored in settings as
 * one JSON row, so two concurrent first requests cannot end up with mismatched halves.
 * @return array{public: string, private_pem: string, subject: string}
 */
function push_vapid(PDO $pdo): array {
    static $vapid = null;
    if ($vapid !== null) return $vapid;

    $read = fn() => $pdo->query("SELECT value FROM settings WHERE key = 'vapid_keys'")->fetchColumn();
    $json = $read();
    if ($json === false) {
        $pdo->prepare("INSERT INTO settings (key, value) VALUES ('vapid_keys', ?) ON CONFLICT (key) DO NOTHING")
            ->execute([json_encode(webpush_generate_vapid_keys())]);
        $json = $read();
    }
    $vapid = json_decode((string)$json, true);

    // Pflichtangabe für Apple/Google: wer schickt? Die eigene Adresse der App.
    $host = parse_url(BASE_URL !== '' ? (str_contains(BASE_URL, '://') ? BASE_URL : 'https://' . BASE_URL) : '', PHP_URL_HOST)
         ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $vapid['subject'] = 'https://' . preg_replace('/:\d+$/', '', $host);
    return $vapid;
}

const PUSH_DEVICE_COOKIE = 'tm_device';

/**
 * Store the push subscription of the current device (for the signed-in account, or as guest
 * device with user_id NULL) and remember the device in a long-lived cookie.
 */
function push_save_subscription(PDO $pdo, ?int $user_id, string $endpoint, string $p256dh, string $auth): void {
    // Admin-Kontext: dasselbe Gerät kann vorher einem anderen Nutzer gehört haben
    $token = as_admin($pdo, function () use ($pdo, $user_id, $endpoint, $p256dh, $auth) {
        $stmt = $pdo->prepare(
            "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, device_token) VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (endpoint) DO UPDATE
                SET user_id = EXCLUDED.user_id, p256dh = EXCLUDED.p256dh, auth = EXCLUDED.auth, updated_at = NOW(),
                    device_token = COALESCE(push_subscriptions.device_token, EXCLUDED.device_token)
             RETURNING device_token"
        );
        $stmt->execute([$user_id, $endpoint, $p256dh, $auth, bin2hex(random_bytes(32))]);
        return (string)$stmt->fetchColumn();
    });
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie(PUSH_DEVICE_COOKIE, $token, [
        'expires' => time() + 400 * 86400, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/** Push device of this browser (from the cookie), or null. */
function push_device_id(PDO $pdo): ?int {
    $token = (string)($_COOKIE[PUSH_DEVICE_COOKIE] ?? '');
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) return null;
    $id = as_admin($pdo, function () use ($pdo, $token) {
        $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE device_token = ?");
        $stmt->execute([$token]);
        return $stmt->fetchColumn();
    });
    return $id === false ? null : (int)$id;
}

/** Whether this device has subscribed to the ticker. */
function push_ticker_is_subscribed(PDO $pdo, int $ticker_id): bool {
    $device = push_device_id($pdo);
    if ($device === null) return false;
    return (bool)as_admin($pdo, function () use ($pdo, $ticker_id, $device) {
        $stmt = $pdo->prepare("SELECT 1 FROM ticker_subscriptions WHERE ticker_id = ? AND subscription_id = ?");
        $stmt->execute([$ticker_id, $device]);
        return $stmt->fetchColumn();
    });
}

/** Subscribe or unsubscribe this device; false when the device is not registered for push. */
function push_ticker_set_subscribed(PDO $pdo, int $ticker_id, bool $on): bool {
    $device = push_device_id($pdo);
    if ($device === null) return false;
    as_admin($pdo, function () use ($pdo, $ticker_id, $device, $on) {
        $pdo->prepare($on
            ? "INSERT INTO ticker_subscriptions (ticker_id, subscription_id) VALUES (?, ?) ON CONFLICT DO NOTHING"
            : "DELETE FROM ticker_subscriptions WHERE ticker_id = ? AND subscription_id = ?"
        )->execute([$ticker_id, $device]);
    });
    return true;
}

/**
 * Claim the start notice of a ticker. Returns true exactly once per ticker, no matter how
 * many requests race for it.
 */
function push_claim_start(PDO $pdo, int $ticker_id): bool {
    $stmt = $pdo->prepare(
        "INSERT INTO ticker_push_state (ticker_id) VALUES (?) ON CONFLICT DO NOTHING RETURNING ticker_id"
    );
    $stmt->execute([$ticker_id]);
    return $stmt->fetchColumn() !== false;
}

/** Run $fn after the response has been sent (PHP-FPM), or at the end of the request otherwise. */
function push_defer(callable $fn): void {
    register_shutdown_function(function () use ($fn) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        try {
            $fn();
        } catch (Throwable $e) {
            error_log('push: ' . $e->getMessage());
        }
    });
}

/**
 * Send one notification to all devices subscribed to a ticker (signed-in or guest), except the
 * author's devices. Runs in admin context. Removes devices the push service reports as gone.
 * The link is the public ticker page; it shows signed-in team members their own view.
 */
function push_ticker_send(PDO $pdo, int $ticker_id, string $title, string $body, ?int $exclude_user_id, int $ttl): void {
    set_admin_context($pdo);
    $stmt = $pdo->prepare(
        "SELECT ps.id, ps.endpoint, ps.p256dh, ps.auth
         FROM ticker_subscriptions ts
         JOIN push_subscriptions ps ON ps.id = ts.subscription_id
         LEFT JOIN users u          ON u.id = ps.user_id
         WHERE ts.ticker_id = ?
           AND (ps.user_id IS NULL OR (u.is_active = TRUE AND ps.user_id <> ?))"
    );
    $stmt->execute([$ticker_id, $exclude_user_id ?? 0]);
    $subs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subs[$row['id']] = $row;
    if (!$subs) return;

    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => "/ticker/{$ticker_id}"],
                           JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $gone = [];
    foreach (webpush_send_all($subs, $payload, push_vapid($pdo), $ttl, 'high') as $id => $status) {
        if ($status === 404 || $status === 410) $gone[] = $id;
    }
    if ($gone) {
        $in = implode(',', array_fill(0, count($gone), '?'));
        $pdo->prepare("DELETE FROM push_subscriptions WHERE id IN ($in)")->execute($gone);
    }
}

/** A new entry was posted. Sends the start notice instead if this is how the ticker starts. */
function push_ticker_entry(PDO $pdo, int $message_id, int $author_id): void {
    set_admin_context($pdo);
    $stmt = $pdo->prepare(
        "SELECT t.id AS ticker_id, t.name, t.status, m.timestamp, m.message, tg.label AS tag
         FROM ticker_messages m
         JOIN tickers t          ON t.id = m.ticker_id
         LEFT JOIN ticker_tags tg ON tg.id = m.tag_id
         WHERE m.id = ?"
    );
    $stmt->execute([$message_id]);
    $ticker = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticker || $ticker['status'] !== 'active') return;
    $ticker_id = (int)$ticker['ticker_id'];

    $text = trim(substr($ticker['timestamp'], 0, 5) . ' ' . ($ticker['tag'] ? $ticker['tag'] . ': ' : '') . $ticker['message']);
    if (push_claim_start($pdo, $ticker_id)) {
        push_ticker_send($pdo, $ticker_id, $ticker['name'] . ' ist live', $text, $author_id, 3600);
    } else {
        push_ticker_send($pdo, $ticker_id, $ticker['name'], $text, $author_id, 900);
    }
}

/**
 * Poor man's scheduler: send start notices whose start time has passed. Called on requests
 * (see public/index.php) and throttled to once a minute via a timestamp file, so it costs
 * nothing on most requests and needs no cron job.
 */
function push_check_due_starts(): void {
    $marker = sys_get_temp_dir() . '/tm-push-' . md5(ROOT_PATH . DB_SCHEMA);
    $last = @filemtime($marker);
    if ($last !== false && $last > time() - 60) return;
    @touch($marker);

    push_defer(function () {
        $pdo = get_db();
        set_admin_context($pdo);
        $stmt = $pdo->prepare(
            "SELECT t.id, t.name, t.description
             FROM tickers t
             JOIN teams tm ON tm.id = t.team_id AND tm.is_active = TRUE
             WHERE t.status = 'active'
               AND t.event_date IS NOT NULL AND t.start_time IS NOT NULL
               AND (t.event_date + t.start_time) AT TIME ZONE 'Europe/Berlin' <= NOW()
               AND (t.event_date + t.start_time) AT TIME ZONE 'Europe/Berlin' > NOW() - make_interval(hours => ?)
               AND NOT EXISTS (SELECT 1 FROM ticker_push_state s WHERE s.ticker_id = t.id)
               AND EXISTS (SELECT 1 FROM ticker_subscriptions ts WHERE ts.ticker_id = t.id)"
        );
        $stmt->execute([PUSH_START_GRACE_HOURS]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            if (push_claim_start($pdo, (int)$t['id'])) {
                $body = trim((string)$t['description']) !== '' ? mb_strimwidth(trim($t['description']), 0, 120, '…') : 'Der Live-Ticker hat begonnen.';
                push_ticker_send($pdo, (int)$t['id'], $t['name'] . ' ist live', $body, null, 3600);
            }
        }
        reset_rls_context($pdo);
    });
}
