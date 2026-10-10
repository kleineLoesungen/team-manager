<?php
// src/utils/calendar.php — Calendar view helpers: week/month boundaries, ICS formatting and team feed

declare(strict_types=1);

/**
 * Calculate calendar month boundaries with offset.
 *
 * @param DateTime $now    Reference date (server's current date in Europe/Berlin)
 * @param int      $offset Months to offset: 0 = current month, -1 = last month, +1 = next month
 * @return array{start: string, end: string, label: string}
 *   start/end: 'Y-m-d' strings for SQL BETWEEN clause
 *   label: formatted German string e.g. "Juli 2026"
 */
function getMonthBoundaries(DateTime $now, int $offset): array
{
    $first = clone $now;
    $first->modify('first day of this month');
    if ($offset > 0) {
        $first->modify('+' . $offset . ' months');
        $first->modify('first day of this month');
    } elseif ($offset < 0) {
        $first->modify($offset . ' months');
        $first->modify('first day of this month');
    }

    $last = clone $first;
    $last->modify('last day of this month');

    $de_months = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März',    4 => 'April',
        5 => 'Mai',    6 => 'Juni',    7 => 'Juli',     8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];

    return [
        'start' => $first->format('Y-m-d'),
        'end'   => $last->format('Y-m-d'),
        'label' => $de_months[(int)$first->format('n')] . ' ' . $first->format('Y'),
    ];
}

/**
 * Escape special characters in ICS field values per RFC 5545 §3.3.11.
 *
 * Must escape: backslash (first!), newlines, comma, semicolon.
 */
function escapeIcsField(string $value): string
{
    $value = str_replace("\\", "\\\\", $value);   // Backslash must be first
    $value = str_replace("\r\n", "\\n", $value);  // CRLF → \n
    $value = str_replace("\n",   "\\n", $value);  // LF → \n
    $value = str_replace(",",    "\\,", $value);  // Comma
    $value = str_replace(";",    "\\;", $value);  // Semicolon
    return $value;
}

/**
 * Fold a single ICS content line to max 75 octets per RFC 5545 §3.1.
 * Does NOT append the trailing CRLF — caller must add "\r\n".
 */
function foldIcsLine(string $line): string
{
    $result = '';
    while (strlen($line) > 75) {          // strlen = byte length for ASCII-safe folding
        $result .= substr($line, 0, 75) . "\r\n ";
        $line    = substr($line, 75);
    }
    return $result . $line;
}

/**
 * How far back the ICS feeds go: lists, events and bookings from today minus this interval on.
 * Older dates stay available in the app. Used inside SQL (PostgreSQL interval).
 */
const ICS_PAST_INTERVAL = "3 months";

/** VALARM trigger: reminder at 08:00 on the day (relative to the start, or to midnight all day). */
function ics_morning_trigger(?string $time_start): string
{
    if ($time_start === null) return 'TRIGGER:PT8H';
    [$h, $m]    = explode(':', $time_start);
    $offset_min = 480 - ((int)$h * 60 + (int)$m);
    return $offset_min < 0 ? 'TRIGGER:-PT' . abs($offset_min) . 'M'
         : ($offset_min > 0 ? 'TRIGGER:PT' . $offset_min . 'M' : 'TRIGGER:PT0S');
}

/**
 * Team feed (lists + events) as an ICS document. Rows need team_id; with $team_prefix the
 * summary starts with the team name ("U13 - Training"). A list row with 'rsvp' => [yes, total]
 * gets the count appended ("U13 - Training (13/14)", coordinator feed). A row's 'dept_icon'
 * (department symbol) goes in front of everything: "⚽ U13 - Training".
 * @param array  $lists     id, team_id, team_name, name, date, location, description, time_start, time_end
 * @param array  $events    id, team_id, team_name, title, description, location, icon, date, is_all_day, time_start, time_end
 * @param string $role_path 'coordinator' or 'member' — links point into this role's pages
 */
function ics_team_calendar(array $lists, array $events, string $base_url, string $role_path, bool $team_prefix = false): string
{
    $dtstamp = gmdate('Ymd\THis\Z');
    $lead    = fn(array $row) => !empty($row['dept_icon']) ? $row['dept_icon'] . ' ' : '';
    $prefix  = fn(array $row) => $team_prefix ? $row['team_name'] . ' - ' : '';

    $out  = "BEGIN:VCALENDAR\r\n";
    $out .= "VERSION:2.0\r\n";
    $out .= "PRODID:-//Team Manager//NONSGML v1.0//DE\r\n";
    $out .= "CALSCALE:GREGORIAN\r\n";
    $out .= "METHOD:PUBLISH\r\n";

    foreach ($lists as $list) {
        $uid      = md5((string)$list['team_id'] . '-' . (string)$list['id']) . '@team-manager.local';
        $list_url = $base_url . '/' . $role_path . '/lists/' . (int)$list['id'];
        $summary  = $lead($list) . $prefix($list) . $list['name']
                  . (isset($list['rsvp']) ? ' (' . (int)$list['rsvp'][0] . '/' . (int)$list['rsvp'][1] . ')' : '');

        if (!empty($list['time_start'])) {
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
            $trigger      = ics_morning_trigger($ts);
        } else {
            $dt_date      = str_replace('-', '', $list['date']);
            $dtstart_line = "DTSTART;VALUE=DATE:{$dt_date}";
            $dtend_line   = "DTEND;VALUE=DATE:{$dt_date}";
            $trigger      = ics_morning_trigger(null);
        }

        $desc_parts = [];
        if (!empty($list['description'])) $desc_parts[] = escapeIcsField($list['description']);
        $desc_parts[] = $list_url;

        $out .= "BEGIN:VEVENT\r\n";
        $out .= "UID:{$uid}\r\n";
        $out .= "DTSTAMP:{$dtstamp}\r\n";
        $out .= foldIcsLine($dtstart_line) . "\r\n";
        $out .= foldIcsLine($dtend_line)   . "\r\n";
        $out .= foldIcsLine("SUMMARY:" . escapeIcsField($summary)) . "\r\n";
        if (!empty($list['location'])) $out .= foldIcsLine("LOCATION:" . escapeIcsField($list['location'])) . "\r\n";
        $out .= foldIcsLine("URL:{$list_url}") . "\r\n";
        $out .= foldIcsLine("DESCRIPTION:" . implode('\\n', $desc_parts)) . "\r\n";
        $out .= "BEGIN:VALARM\r\n{$trigger}\r\nACTION:DISPLAY\r\n" . foldIcsLine("DESCRIPTION:Erinnerung: " . escapeIcsField($summary)) . "\r\nEND:VALARM\r\n";
        $out .= "END:VEVENT\r\n";
    }

    $icon_emoji = [
        'bi-calendar-event' => '📅', 'bi-people-fill' => '👥', 'bi-star-fill' => '⭐',
        'bi-gift-fill' => '🎁', 'bi-chat-dots-fill' => '💬', 'bi-flag-fill' => '🚩',
        'bi-question-circle-fill' => '❓',
    ];

    foreach ($events as $ev) {
        $uid     = md5('event-' . $ev['team_id'] . '-' . $ev['id']) . '@team-manager.local';
        $summary = $lead($ev) . ($icon_emoji[$ev['icon'] ?? ''] ?? '📅') . ' ' . $prefix($ev) . $ev['title'];
        $dt_date = str_replace('-', '', $ev['date']);
        $all_day = in_array($ev['is_all_day'], [true, 1, '1', 't', 'true'], true);

        if (!$all_day && !empty($ev['time_start'])) {
            $ts       = substr((string)$ev['time_start'], 0, 5);
            $dt_start = $dt_date . 'T' . str_replace(':', '', $ts) . '00';
            if (!empty($ev['time_end'])) {
                $dt_end = $dt_date . 'T' . str_replace(':', '', substr((string)$ev['time_end'], 0, 5)) . '00';
            } else {
                $d = new DateTime($ev['date'] . ' ' . $ts);
                $d->modify('+1 hour');
                $dt_end = $d->format('Ymd\THis');
            }
            $dtstart_line = "DTSTART;TZID=Europe/Berlin:{$dt_start}";
            $dtend_line   = "DTEND;TZID=Europe/Berlin:{$dt_end}";
            $trigger      = ics_morning_trigger($ts);
        } else {
            $dtstart_line = "DTSTART;VALUE=DATE:{$dt_date}";
            $dtend_line   = "DTEND;VALUE=DATE:{$dt_date}";
            $trigger      = ics_morning_trigger(null);
        }

        $out .= "BEGIN:VEVENT\r\n";
        $out .= "UID:{$uid}\r\n";
        $out .= "DTSTAMP:{$dtstamp}\r\n";
        $out .= foldIcsLine($dtstart_line) . "\r\n";
        $out .= foldIcsLine($dtend_line)   . "\r\n";
        $out .= foldIcsLine("SUMMARY:" . escapeIcsField($summary)) . "\r\n";
        if (!empty($ev['location'])) $out .= foldIcsLine("LOCATION:" . escapeIcsField($ev['location'])) . "\r\n";
        if (!empty($ev['description'])) $out .= foldIcsLine("DESCRIPTION:" . escapeIcsField($ev['description'])) . "\r\n";
        $out .= "BEGIN:VALARM\r\n{$trigger}\r\nACTION:DISPLAY\r\n" . foldIcsLine("DESCRIPTION:Erinnerung: " . escapeIcsField($summary)) . "\r\nEND:VALARM\r\n";
        $out .= "END:VEVENT\r\n";
    }

    return $out . "END:VCALENDAR\r\n";
}
