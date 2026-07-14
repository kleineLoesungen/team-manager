<?php
// src/ics_handler.php — GET /ics/{team_id}.ics — Public ICS calendar export, no auth required
// Decisions: D-10 (public, no token), D-11 (URL schema), D-12 (on-demand), D-13 (public+protected lists only)

declare(strict_types=1);

// No require_coordinator() or require_member() — this endpoint is intentionally public (D-10)

require_once ROOT_PATH . '/src/utils/calendar.php';

$team_id = (int)($_REQUEST['team_id'] ?? 0);

if ($team_id <= 0) {
    http_response_code(404);
    exit;
}

$pdo = get_db();

// Set RLS context so public/protected lists are visible without auth (D-10)
set_team_context($pdo, $team_id);

// Verify team exists and is active
$team_stmt = $pdo->prepare(
    "SELECT id FROM teams WHERE id = ? AND is_active = TRUE"
);
$team_stmt->execute([$team_id]);
if (!$team_stmt->fetch()) {
    http_response_code(404);
    exit;
}

// Fetch public + protected lists with a date (D-13: private lists excluded)
$stmt = $pdo->prepare(
    "SELECT id, name, date, location
     FROM lists
     WHERE team_id = ?
       AND date IS NOT NULL
       AND visibility IN ('public', 'protected')
     ORDER BY date ASC"
);
$stmt->execute([$team_id]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Send ICS headers — no caching (D-12: on-demand generation)
header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="team-' . $team_id . '.ics"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Build RFC 5545 VCALENDAR — ALL line endings MUST be CRLF (\r\n)
$dtstamp = gmdate('Ymd\THis\Z'); // UTC timestamp of ICS generation (required per RFC 5545)

$out  = "BEGIN:VCALENDAR\r\n";
$out .= "VERSION:2.0\r\n";
$out .= "PRODID:-//Team Manager//NONSGML v1.0//DE\r\n";
$out .= "CALSCALE:GREGORIAN\r\n";
$out .= "METHOD:PUBLISH\r\n";

foreach ($lists as $list) {
    // DTSTART;VALUE=DATE uses YYYYMMDD format (all-day event, no time component)
    $dt_date = str_replace('-', '', $list['date']);

    // UID: stable per-list-per-team identifier for calendar app deduplication
    $uid = md5((string)$team_id . '-' . (string)$list['id']) . '@team-manager.local';

    $out .= "BEGIN:VEVENT\r\n";
    $out .= "UID:{$uid}\r\n";
    $out .= "DTSTAMP:{$dtstamp}\r\n";
    $out .= "DTSTART;VALUE=DATE:{$dt_date}\r\n";
    $out .= "DTEND;VALUE=DATE:{$dt_date}\r\n";   // All-day: end = start for single-day events
    $out .= "SUMMARY:" . escapeIcsField($list['name']) . "\r\n";
    if (!empty($list['location'])) {
        $out .= "LOCATION:" . escapeIcsField($list['location']) . "\r\n";
    }
    $out .= "END:VEVENT\r\n";
}

$out .= "END:VCALENDAR\r\n";

echo $out;
exit;
