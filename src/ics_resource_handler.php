<?php
// src/ics_resource_handler.php — GET /ics/resource/{token}.ics — Belegung einer Ressource als Kalender
// Kein Login: das Token steht für genau eine Ressource. Alle Teams; Titel nur, wenn ihn die
// Mitglieder des belegenden Teams sehen dürfen, sonst "Belegt". Ab 90 Tagen zurück.

declare(strict_types=1);

require_once ROOT_PATH . '/src/utils/calendar.php';
require_once ROOT_PATH . '/src/db/resources.php';

$raw_token = $_REQUEST['cal_token'] ?? '';
if (!preg_match('/^[0-9a-f]{64}$/', $raw_token)) {
    http_response_code(404);
    exit;
}

$pdo = get_db();
set_admin_context($pdo);   // Token prüfen und Belegung aller Teams lesen

$stmt = $pdo->prepare("SELECT id, name FROM resources WHERE calendar_token = ? AND is_active = TRUE");
$stmt->execute([$raw_token]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$resource) {
    reset_rls_context($pdo);
    http_response_code(404);
    exit;
}

$slots = resources_slots_sql();
$stmt  = $pdo->prepare(
    "WITH s AS ($slots)
     SELECT * FROM s WHERE resource_id = ? AND ends_at > CURRENT_DATE - 90
     ORDER BY starts_at"
);
$stmt->execute([(int)$resource['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
reset_rls_context($pdo);

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="ressource-' . (int)$resource['id'] . '.ics"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$dtstamp = gmdate('Ymd\THis\Z');
$out  = "BEGIN:VCALENDAR\r\n";
$out .= "VERSION:2.0\r\n";
$out .= "PRODID:-//Team Manager//NONSGML v1.0//DE\r\n";
$out .= "CALSCALE:GREGORIAN\r\n";
$out .= "METHOD:PUBLISH\r\n";
$out .= foldIcsLine("X-WR-CALNAME:" . escapeIcsField($resource['name'])) . "\r\n";

foreach ($rows as $r) {
    $shared  = in_array($r['shared'], [true, 1, '1', 't', 'true'], true);
    $all_day = in_array($r['all_day'], [true, 1, '1', 't', 'true'], true);
    $summary = $r['team_name'] . ': ' . ($shared ? $r['title'] : 'Belegt');
    $start   = new DateTimeImmutable((string)$r['starts_at']);
    $end     = new DateTimeImmutable((string)$r['ends_at']);

    $out .= "BEGIN:VEVENT\r\n";
    $out .= "UID:" . md5('resource-' . (int)$r['booking_id']) . "@team-manager.local\r\n";
    $out .= "DTSTAMP:{$dtstamp}\r\n";
    if ($all_day) {
        $out .= "DTSTART;VALUE=DATE:" . $start->format('Ymd') . "\r\n";
        $out .= "DTEND;VALUE=DATE:"   . $end->format('Ymd')   . "\r\n";
    } else {
        $out .= "DTSTART;TZID=Europe/Berlin:" . $start->format('Ymd\THis') . "\r\n";
        $out .= "DTEND;TZID=Europe/Berlin:"   . $end->format('Ymd\THis')   . "\r\n";
    }
    $out .= foldIcsLine("SUMMARY:" . escapeIcsField($summary)) . "\r\n";
    $out .= foldIcsLine("LOCATION:" . escapeIcsField($resource['name'])) . "\r\n";
    $out .= "END:VEVENT\r\n";
}

$out .= "END:VCALENDAR\r\n";
echo $out;
exit;
