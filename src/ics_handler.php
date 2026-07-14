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
// Guard time columns — DB_HAS_LIST_TIMES is defined by maybe_migrate_db() inside get_db() above
if (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) {
    $stmt = $pdo->prepare(
        "SELECT id, name, date, location, description, time_start, time_end
         FROM lists
         WHERE team_id = ?
           AND date IS NOT NULL
           AND visibility IN ('public', 'protected')
         ORDER BY date ASC"
    );
} else {
    $stmt = $pdo->prepare(
        "SELECT id, name, date, location, description
         FROM lists
         WHERE team_id = ?
           AND date IS NOT NULL
           AND visibility IN ('public', 'protected')
         ORDER BY date ASC"
    );
}
$stmt->execute([$team_id]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Send ICS headers — no caching (D-12: on-demand generation)
header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="team-' . $team_id . '.ics"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Build RFC 5545 VCALENDAR — ALL line endings MUST be CRLF (\r\n)
$dtstamp = gmdate('Ymd\THis\Z'); // UTC timestamp of ICS generation (required per RFC 5545)

// Base URL for event links (URL and DESCRIPTION properties)
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $scheme . '://' . $host;

$out  = "BEGIN:VCALENDAR\r\n";
$out .= "VERSION:2.0\r\n";
$out .= "PRODID:-//Team Manager//NONSGML v1.0//DE\r\n";
$out .= "CALSCALE:GREGORIAN\r\n";
$out .= "METHOD:PUBLISH\r\n";

foreach ($lists as $list) {
    // UID: stable per-list-per-team identifier for calendar app deduplication
    $uid      = md5((string)$team_id . '-' . (string)$list['id']) . '@team-manager.local';
    $role     = (($_REQUEST['role'] ?? '') === 'member') ? 'member' : 'coordinator';
    $list_url = $base_url . '/' . $role . '/lists/' . (int)$list['id'];

    // DTSTART / DTEND — use datetime when time_start is available, otherwise all-day
    $has_time = !empty($list['time_start']) && defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES;

    if ($has_time) {
        $ts = substr((string)$list['time_start'], 0, 5);   // "HH:MM" from "HH:MM:SS"
        $dt_start = str_replace('-', '', $list['date']) . 'T' . str_replace(':', '', $ts) . '00';

        if (!empty($list['time_end'])) {
            $te = substr((string)$list['time_end'], 0, 5);
            $dt_end = str_replace('-', '', $list['date']) . 'T' . str_replace(':', '', $te) . '00';
        } else {
            // Default duration: 1 hour
            $end_dt = new DateTime($list['date'] . ' ' . $ts);
            $end_dt->modify('+1 hour');
            $dt_end = $end_dt->format('Ymd\THis');
        }
        $dtstart_line = "DTSTART:{$dt_start}";
        $dtend_line   = "DTEND:{$dt_end}";
    } else {
        $dt_date      = str_replace('-', '', $list['date']);
        $dtstart_line = "DTSTART;VALUE=DATE:{$dt_date}";
        $dtend_line   = "DTEND;VALUE=DATE:{$dt_date}";
    }

    // DESCRIPTION: list description + URL (clients that ignore URL still see the link)
    $desc_parts = [];
    if (!empty($list['description'])) {
        $desc_parts[] = escapeIcsField($list['description']);
    }
    $desc_parts[] = $list_url;    // Plain text URL — no escaping needed for http URLs
    $description_value = implode('\\n', $desc_parts);

    $out .= "BEGIN:VEVENT\r\n";
    $out .= "UID:{$uid}\r\n";
    $out .= "DTSTAMP:{$dtstamp}\r\n";
    $out .= foldIcsLine($dtstart_line) . "\r\n";
    $out .= foldIcsLine($dtend_line)   . "\r\n";
    $out .= "SUMMARY:" . escapeIcsField($list['name']) . "\r\n";
    if (!empty($list['location'])) {
        $out .= "LOCATION:" . escapeIcsField($list['location']) . "\r\n";
    }
    $out .= foldIcsLine("URL:{$list_url}") . "\r\n";
    $out .= foldIcsLine("DESCRIPTION:{$description_value}") . "\r\n";
    $out .= "END:VEVENT\r\n";
}

$out .= "END:VCALENDAR\r\n";

echo $out;
exit;
