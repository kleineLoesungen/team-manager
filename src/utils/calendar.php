<?php
// src/utils/calendar.php — Calendar view helpers: week/month boundaries + ICS field escaping

declare(strict_types=1);

/**
 * Calculate ISO week boundaries (Monday–Sunday) with offset.
 *
 * @param DateTime $now    Reference date (server's current date in Europe/Berlin)
 * @param int      $offset Weeks to offset: 0 = current week, -1 = last week, +1 = next week
 * @return array{start: string, end: string, label: string}
 *   start/end: 'Y-m-d' strings for SQL BETWEEN clause
 *   label: formatted German string e.g. "14.–20. Juli 2026"
 */
function getWeekBoundaries(DateTime $now, int $offset): array
{
    $monday = clone $now;
    $monday->modify('Monday this week');
    if ($offset !== 0) {
        $monday->modify(($offset > 0 ? '+' : '') . $offset . ' weeks');
    }
    $sunday = clone $monday;
    $sunday->modify('+6 days');

    $de_months = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März',    4 => 'April',
        5 => 'Mai',    6 => 'Juni',    7 => 'Juli',     8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];

    // If Monday and Sunday are in the same month: "14.–20. Juli 2026"
    // If they span months: "28. Juli–3. August 2026"
    $mon_month = (int)$monday->format('n');
    $sun_month = (int)$sunday->format('n');

    if ($mon_month === $sun_month) {
        $label = $monday->format('d.') . '–' . $sunday->format('d.') . ' '
               . $de_months[$sun_month] . ' ' . $sunday->format('Y');
    } else {
        $label = $monday->format('d.') . ' ' . $de_months[$mon_month]
               . '–' . $sunday->format('d.') . ' ' . $de_months[$sun_month]
               . ' ' . $sunday->format('Y');
    }

    return [
        'start' => $monday->format('Y-m-d'),
        'end'   => $sunday->format('Y-m-d'),
        'label' => $label,
    ];
}

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
