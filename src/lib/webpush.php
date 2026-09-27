<?php
// src/lib/webpush.php — Web Push ohne Composer, nur ext-openssl und ext-curl
//
//   RFC 8030  Zustellung über den Push-Dienst des Browsers (Apple, Google, Mozilla, Microsoft)
//   RFC 8291  Inhalt Ende-zu-Ende verschlüsselt (aes128gcm) — der Push-Dienst kann ihn nicht lesen
//   RFC 8292  VAPID: der Server weist sich mit einem eigenen Schlüsselpaar aus
//
// Reines Protokoll, keine App-Logik. Wer was bekommt, steht in src/push/ticker_push.php.

declare(strict_types=1);

// DER-Kopf einer SubjectPublicKeyInfo für einen unkomprimierten P-256-Punkt (65 Byte folgen)
const WEBPUSH_P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

// Push-Dienste, an die der Server senden darf. Der Endpunkt kommt vom Browser; ohne diese
// Liste könnte ein Angemeldeter den Server beliebige URLs aufrufen lassen (SSRF).
const WEBPUSH_ALLOWED_HOSTS = [
    'fcm.googleapis.com',          // Chrome, Edge (Android), Samsung Internet, Opera
    'push.apple.com',              // Safari auf iOS/iPadOS/macOS (web.push.apple.com)
    'push.services.mozilla.com',   // Firefox
    'notify.windows.com',          // Edge (Windows)
];

function b64u_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64u_decode(string $data): string {
    $raw = base64_decode(strtr($data, '-_', '+/'), true);
    return $raw === false ? '' : $raw;
}

function webpush_endpoint_allowed(string $endpoint): bool {
    $p = parse_url($endpoint);
    if (($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['port'])) {
        return false;
    }
    $host = strtolower($p['host']);
    foreach (WEBPUSH_ALLOWED_HOSTS as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
            return true;
        }
    }
    return false;
}

/** Uncompressed P-256 point (0x04 || X || Y) of an OpenSSL key. */
function webpush_point($key): string {
    $ec = openssl_pkey_get_details($key)['ec'];
    return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
}

function webpush_new_ec_key() {
    return openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
}

/**
 * Fresh VAPID key pair. 'public' goes to the browser (applicationServerKey),
 * 'private_pem' stays on the server.
 * @return array{public: string, private_pem: string}
 */
function webpush_generate_vapid_keys(): array {
    $key = webpush_new_ec_key();
    openssl_pkey_export($key, $pem);
    return ['public' => b64u_encode(webpush_point($key)), 'private_pem' => $pem];
}

function webpush_public_key_from_point(string $point) {
    $der = hex2bin(WEBPUSH_P256_SPKI_PREFIX) . $point;
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    return openssl_pkey_get_public($pem);
}

/** ECDSA signature: OpenSSL returns DER, JWT (ES256) wants raw r || s with 32 bytes each. */
function webpush_der_to_raw_signature(string $der): string {
    $offset = 2;                                   // SEQUENCE tag + length (short form for P-256)
    $raw = '';
    for ($i = 0; $i < 2; $i++) {                   // INTEGER r, INTEGER s
        $len = ord($der[$offset + 1]);
        $int = ltrim(substr($der, $offset + 2, $len), "\0");
        $raw .= str_pad($int, 32, "\0", STR_PAD_LEFT);
        $offset += 2 + $len;
    }
    return $raw;
}

/** Authorization header for one push service origin (RFC 8292). */
function webpush_vapid_authorization(string $endpoint, array $vapid): string {
    $p = parse_url($endpoint);
    $claims = [
        'aud' => $p['scheme'] . '://' . $p['host'],
        'exp' => time() + 12 * 3600,
        'sub' => $vapid['subject'],
    ];
    $signing_input = b64u_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
                   . '.' . b64u_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));
    openssl_sign($signing_input, $der, $vapid['private_pem'], OPENSSL_ALGO_SHA256);
    return 'vapid t=' . $signing_input . '.' . b64u_encode(webpush_der_to_raw_signature($der))
         . ', k=' . $vapid['public'];
}

/**
 * Encrypt a payload for one subscription (RFC 8291 / RFC 8188, single record).
 * @param string $ua_public_b64u Subscription key "p256dh" (base64url)
 * @param string $auth_b64u      Subscription key "auth" (base64url)
 */
function webpush_encrypt(string $payload, string $ua_public_b64u, string $auth_b64u): string {
    $ua_public = b64u_decode($ua_public_b64u);
    $auth      = b64u_decode($auth_b64u);
    if (strlen($ua_public) !== 65 || $ua_public[0] !== "\x04" || strlen($auth) !== 16) {
        throw new InvalidArgumentException('Ungültige Push-Subscription-Schlüssel');
    }

    $ephemeral = webpush_new_ec_key();
    $as_public = webpush_point($ephemeral);
    $shared    = openssl_pkey_derive(webpush_public_key_from_point($ua_public), $ephemeral, 32);
    if ($shared === false) {
        throw new RuntimeException('ECDH fehlgeschlagen');
    }

    $ikm   = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $ua_public . $as_public, $auth);
    $salt  = random_bytes(16);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

    // 0x02 = Trennzeichen des letzten (einzigen) Records, keine Auffüllung
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

    // Header: salt(16) | record size(4) | key id length(1) | key id = as_public(65)
    return $salt . pack('N', 4096) . chr(65) . $as_public . $cipher . $tag;
}

/**
 * Send one payload to many subscriptions in parallel.
 * @param array<int, array{endpoint: string, p256dh: string, auth: string}> $subscriptions
 * @param array{public: string, private_pem: string, subject: string} $vapid
 * @return array<int, int> HTTP status per key of $subscriptions (0 = not sent / network error).
 *         201 = accepted; 404/410 = subscription is gone and should be deleted.
 */
function webpush_send_all(array $subscriptions, string $payload, array $vapid, int $ttl, string $urgency = 'normal'): array {
    $status = [];
    $handles = [];
    $auth_by_origin = [];
    $multi = curl_multi_init();

    foreach ($subscriptions as $key => $sub) {
        $status[$key] = 0;
        if (!webpush_endpoint_allowed($sub['endpoint'])) {
            continue;
        }
        try {
            $body = webpush_encrypt($payload, $sub['p256dh'], $sub['auth']);
        } catch (Throwable $e) {
            error_log('webpush: ' . $e->getMessage());
            continue;
        }
        $origin = parse_url($sub['endpoint'], PHP_URL_HOST);
        $auth_by_origin[$origin] ??= webpush_vapid_authorization($sub['endpoint'], $vapid);

        $ch = curl_init($sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: ' . $ttl,
                'Urgency: ' . $urgency,
                'Authorization: ' . $auth_by_origin[$origin],
            ],
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$key] = $ch;
    }

    do {
        $result = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running && $result === CURLM_OK);

    foreach ($handles as $key => $ch) {
        $status[$key] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($status[$key] >= 400) {
            error_log('webpush: ' . parse_url($subscriptions[$key]['endpoint'], PHP_URL_HOST)
                . ' antwortet ' . $status[$key] . ': ' . substr((string)curl_multi_getcontent($ch), 0, 200));
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);
    return $status;
}
