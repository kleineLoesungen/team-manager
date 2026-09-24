<?php
// src/ics_token_handler.php - GET /ics/{token}.ics - Team-scoped ICS feed (coordinator or member token)
// Token identifies a TEAM + ROLE scope (2 tokens per team, not per user). No session required.

declare(strict_types=1);

require_once ROOT_PATH . '/src/utils/calendar.php';

$raw_token = $_REQUEST['cal_token'] ?? '';

// Reject obviously invalid tokens early (must be 64 lowercase hex chars)
if (!preg_match('/^[0-9a-f]{64}$/', $raw_token)) {
    http_response_code(404);
    exit;
}

$pdo = get_db();

// Resolve team + role by token - admin context bypasses RLS (token lookup is credential verification)
set_admin_context($pdo);
$team_stmt = $pdo->prepare(
    "SELECT id, 'coordinator' AS role FROM teams WHERE calendar_token_coordinator = ? AND is_active = TRUE
     UNION ALL
     SELECT id, 'member' AS role FROM teams WHERE calendar_token_member = ? AND is_active = TRUE
     LIMIT 1"
);
$team_stmt->execute([$raw_token, $raw_token]);
$cal_team = $team_stmt->fetch(PDO::FETCH_ASSOC);
reset_rls_context($pdo);

if (!$cal_team) {
    http_response_code(404);
    exit;
}

$team_id = (int)$cal_team['id'];
$role    = $cal_team['role']; // 'coordinator' or 'member'

// Set scoped context for data fetches - team-wide token, no individual user
set_team_context($pdo, $team_id, $role);

$is_coordinator = ($role === 'coordinator');

// Lists: coordinators see all; members see public + protected only
$vis_clause = $is_coordinator ? "visibility IN ('public','protected','private')" : "visibility IN ('public','protected')";
$stmt = $pdo->prepare(
    "SELECT id, name, date, location, description, time_start, time_end
     FROM lists
     WHERE team_id = ? AND date IS NOT NULL AND {$vis_clause}
     ORDER BY date ASC"
);
$stmt->execute([$team_id]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Events: coordinators see protected + private; members see protected only
$ev_vis = $is_coordinator ? "visibility IN ('protected','private')" : "visibility = 'protected'";
$e_stmt = $pdo->prepare(
    "SELECT id, title, description, location, icon, date, is_all_day, time_start, time_end
     FROM events
     WHERE team_id = ? AND {$ev_vis} AND date IS NOT NULL
     ORDER BY date ASC"
);
$e_stmt->execute([$team_id]);
$events = $e_stmt->fetchAll(PDO::FETCH_ASSOC);

// ICS output - identical rendering to before
header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="team-' . $team_id . '.ics"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$dtstamp  = gmdate('Ymd\THis\Z');
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $scheme . '://' . $host;
$role_path = $is_coordinator ? 'coordinator' : 'member';

$out  = "BEGIN:VCALENDAR\r\n";
$out .= "VERSION:2.0\r\n";
$out .= "PRODID:-//Team Manager//NONSGML v1.0//DE\r\n";
$out .= "CALSCALE:GREGORIAN\r\n";
$out .= "METHOD:PUBLISH\r\n";

foreach ($lists as $list) {
    $uid      = md5((string)$team_id . '-' . (string)$list['id']) . '@team-manager.local';
    $list_url = $base_url . '/' . $role_path . '/lists/' . (int)$list['id'];
    $has_time = !empty($list['time_start']);

    if ($has_time) {
        $ts       = substr((string)$list['time_start'], 0, 5);
        $dt_start = str_replace('-', '', $list['date']) . 'T' . str_replace(':', '', $ts) . '00';
        if (!empty($list['time_end'])) {
            $te     = substr((string)$list['time_end'], 0, 5);
            $dt_end = str_replace('-', '', $list['date']) . 'T' . str_replace(':', '', $te) . '00';
        } else {
            $end_dt = new DateTime($list['date'] . ' ' . $ts);
            $end_dt->modify('+1 hour');
            $dt_end = $end_dt->format('Ymd\THis');
        }
        $dtstart_line = "DTSTART:{$dt_start}";
        $dtend_line   = "DTEND:{$dt_end}";
        [$h, $m]    = explode(':', $ts);
        $offset_min = 480 - ((int)$h * 60 + (int)$m);
        $trigger    = $offset_min < 0 ? 'TRIGGER:-PT' . abs($offset_min) . 'M'
                    : ($offset_min > 0 ? 'TRIGGER:PT' . $offset_min . 'M' : 'TRIGGER:PT0S');
    } else {
        $dt_date      = str_replace('-', '', $list['date']);
        $dtstart_line = "DTSTART;VALUE=DATE:{$dt_date}";
        $dtend_line   = "DTEND;VALUE=DATE:{$dt_date}";
        $trigger      = 'TRIGGER:PT8H';
    }

    $desc_parts = [];
    if (!empty($list['description'])) $desc_parts[] = escapeIcsField($list['description']);
    $desc_parts[] = $list_url;

    $out .= "BEGIN:VEVENT\r\n";
    $out .= "UID:{$uid}\r\n";
    $out .= "DTSTAMP:{$dtstamp}\r\n";
    $out .= foldIcsLine($dtstart_line) . "\r\n";
    $out .= foldIcsLine($dtend_line)   . "\r\n";
    $out .= "SUMMARY:" . escapeIcsField($list['name']) . "\r\n";
    if (!empty($list['location'])) $out .= "LOCATION:" . escapeIcsField($list['location']) . "\r\n";
    $out .= foldIcsLine("URL:{$list_url}") . "\r\n";
    $out .= foldIcsLine("DESCRIPTION:" . implode('\\n', $desc_parts)) . "\r\n";
    $out .= "BEGIN:VALARM\r\n{$trigger}\r\nACTION:DISPLAY\r\nDESCRIPTION:Erinnerung: " . escapeIcsField($list['name']) . "\r\nEND:VALARM\r\n";
    $out .= "END:VEVENT\r\n";
}

$icon_emoji = [
    'bi-calendar-event' => '📅', 'bi-people-fill' => '👥', 'bi-star-fill' => '⭐',
    'bi-gift-fill' => '🎁', 'bi-chat-dots-fill' => '💬', 'bi-flag-fill' => '🚩',
    'bi-question-circle-fill' => '❓',
];

foreach ($events as $ev) {
    $uid     = md5('event-' . $team_id . '-' . $ev['id']) . '@team-manager.local';
    $summary = ($icon_emoji[$ev['icon'] ?? ''] ?? '📅') . ' ' . $ev['title'];
    $dt_date = str_replace('-', '', $ev['date']);

    if (!$ev['is_all_day'] && !empty($ev['time_start'])) {
        $ts       = substr((string)$ev['time_start'], 0, 5);
        $dt_start = $dt_date . 'T' . str_replace(':', '', $ts) . '00';
        $dt_end   = !empty($ev['time_end'])
            ? $dt_date . 'T' . str_replace(':', '', substr((string)$ev['time_end'], 0, 5)) . '00'
            : (function() use ($ev, $ts) { $d = new DateTime($ev['date'] . ' ' . $ts); $d->modify('+1 hour'); return $d->format('Ymd\THis'); })();
        $dtstart_line = "DTSTART;TZID=Europe/Berlin:{$dt_start}";
        $dtend_line   = "DTEND;TZID=Europe/Berlin:{$dt_end}";
        [$h, $m]    = explode(':', $ts);
        $offset_min = 480 - ((int)$h * 60 + (int)$m);
        $valarm_trigger = $offset_min < 0 ? 'TRIGGER:-PT' . abs($offset_min) . 'M'
                        : ($offset_min > 0 ? 'TRIGGER:PT' . $offset_min . 'M' : 'TRIGGER:PT0S');
    } else {
        $dtstart_line   = "DTSTART;VALUE=DATE:{$dt_date}";
        $dtend_line     = "DTEND;VALUE=DATE:{$dt_date}";
        $valarm_trigger = 'TRIGGER:PT8H';
    }

    $out .= "BEGIN:VEVENT\r\n";
    $out .= "UID:{$uid}\r\n";
    $out .= "DTSTAMP:{$dtstamp}\r\n";
    $out .= foldIcsLine($dtstart_line) . "\r\n";
    $out .= foldIcsLine($dtend_line)   . "\r\n";
    $out .= "SUMMARY:" . escapeIcsField($summary) . "\r\n";
    if (!empty($ev['location'])) $out .= foldIcsLine("LOCATION:" . escapeIcsField($ev['location'])) . "\r\n";
    if (!empty($ev['description'])) $out .= foldIcsLine("DESCRIPTION:" . escapeIcsField($ev['description'])) . "\r\n";
    $out .= "BEGIN:VALARM\r\n{$valarm_trigger}\r\nACTION:DISPLAY\r\nDESCRIPTION:Erinnerung: " . escapeIcsField($summary) . "\r\nEND:VALARM\r\n";
    $out .= "END:VEVENT\r\n";
}

$out .= "END:VCALENDAR\r\n";
echo $out;
exit;
