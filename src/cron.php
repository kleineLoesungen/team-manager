<?php
// src/cron.php — POST /cron — zeitgesteuerte Aufgaben, stündlich von außen aufgerufen (z. B. Cronicle)
//
// Geschützt durch CRON_TOKEN aus der config.php der Instanz, übergeben im Header X-Cron-Token
// (nicht in der URL, damit es in keinem Zugriffsprotokoll steht). Ohne CRON_TOKEN ist der
// Endpunkt aus (404). Antwortet mit JSON, damit der Cron-Dienst Erfolg und Fehler sieht.
//
// Stündlich:  Erinnerung an den Anmeldeschluss (src/push/auto_push.php)
// Nachts (der erste Lauf ab CRON_NIGHTLY_HOUR, einmal pro Tag):
//             Push-Geräte ohne Lebenszeichen entfernen, Update-Prüfung gegen GitHub,
//             abgelaufene Sitzungen und alte Ticker-Zuschauer aufräumen.
// Fällt ein Lauf aus, holt der nächste nach: Erinnerungen hängen am Zeitpunkt, nicht am Lauf,
// und „nachts“ heißt „heute noch nicht gelaufen und schon nach 3 Uhr“.
// Nicht hier: die automatische Statusumstellung von Listen (gilt bei jedem Seitenaufruf, damit
// eine Frist sofort greift) und die Ticker-Startmeldung (kommt mit dem ersten Eintrag).

declare(strict_types=1);

require_once ROOT_PATH . '/src/push/auto_push.php';
require_once ROOT_PATH . '/src/utils/updates.php';

const CRON_NIGHTLY_HOUR = 3;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$token = defined('CRON_TOKEN') ? (string)CRON_TOKEN : '';
if ($token === '') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'CRON_TOKEN ist nicht gesetzt']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !hash_equals($token, (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Kein Zugriff']);
    exit;
}

set_time_limit(300);
$pdo    = get_db();
$state  = cron_state($pdo);
$now    = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
$result = ['ok' => true, 'time' => $now->format('c')];

try {
    $result['reminders'] = list_reminders_send($pdo);

    if (($state['nightly'] ?? '') !== $now->format('Y-m-d') && (int)$now->format('G') >= CRON_NIGHTLY_HOUR) {
        $result['devices_removed']  = push_devices_cleanup($pdo);
        $result['sessions_removed'] = cron_sessions_cleanup();
        $result['viewers_removed']  = as_admin($pdo, fn() =>
            $pdo->exec("DELETE FROM ticker_viewers WHERE last_seen < NOW() - INTERVAL '1 hour'"));
        $update = update_status($pdo, true);
        $result['update_check'] = $update['ok'] ? ($update['newer'] ? 'Update verfügbar' : 'aktuell') : 'nicht erreichbar';
        $state['nightly'] = $now->format('Y-m-d');
    }
} catch (Throwable $e) {
    error_log('cron: ' . $e->getMessage());
    $result = ['ok' => false, 'error' => $e->getMessage()] + $result;
    http_response_code(500);
}

$state['last_run'] = time();
$state['last_ok']  = $result['ok'];
cron_state_save($pdo, $state);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;

/** Delete session files that outlived the longest session (own directory only). */
function cron_sessions_cleanup(): int {
    $dir = defined('SESSION_SAVE_PATH') ? (string)SESSION_SAVE_PATH : '';
    if ($dir === '' || !is_dir($dir)) return 0;
    $limit   = time() - max((int)SESSION_TIMEOUT, (int)SESSION_MEMBER_TIMEOUT);
    $removed = 0;
    foreach (glob($dir . '/sess_*') ?: [] as $file) {
        if (@filemtime($file) < $limit && @unlink($file)) $removed++;
    }
    return $removed;
}
