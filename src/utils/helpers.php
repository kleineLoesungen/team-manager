<?php
// src/utils/helpers.php — Shared utility functions

/**
 * HTML-escape a value for safe output. Always use this in templates.
 * Usage: <?= e($user['name']) ?>
 */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to a URL and stop execution.
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * Build a fully-qualified app URL (https://{BASE_URL}/{path}).
 * Falls back to a root-relative path when BASE_URL is not set (local dev).
 */
function app_url(string $path = ''): string {
    $base = defined('BASE_URL') && BASE_URL !== '' ? 'https://' . rtrim((string)BASE_URL, '/') : '';
    return $base . '/' . ltrim($path, '/');
}

/**
 * Generate a random password using a safe character set.
 * Avoids visually confusing characters (0/O, 1/l/I).
 * Default length: 12 characters.
 */
function generate_random_password(int $length = 12): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $bytes = random_bytes($length);
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[ord($bytes[$i]) % strlen($chars)];
    }
    return $password;
}

/**
 * Generate a username from first/last name initials + 4-digit random number.
 * Format: mm4821 (per D-11). Lowercase initials only.
 */
function generate_username(string $first_name, string $last_name): string {
    $initials = strtolower(mb_substr($first_name, 0, 1)) . strtolower(mb_substr($last_name, 0, 1));
    $number   = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    return $initials . $number;
}

/**
 * Generate a unique username: tries up to 10 times before giving up.
 * Requires a PDO instance to check for collisions.
 */
function generate_unique_username(PDO $pdo, string $first_name, string $last_name): string {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $username = generate_username($first_name, $last_name);
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            return $username;
        }
    }
    // Fallback: append timestamp fragment to ensure uniqueness
    $initials = strtolower(mb_substr($first_name, 0, 1)) . strtolower(mb_substr($last_name, 0, 1));
    return $initials . substr((string)time(), -4);
}

function generate_calendar_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Default value of a global column in a new list, as stored in list_global_columns.default_value
 * and written into the members' cells. Booleans: '1' if ticked, else '0'; numbers: the entered
 * number, else '0'; text: no default (null).
 * @param array $defaults Raw form input [column_id => value]
 */
function list_default_value(?string $data_type, array $defaults, int $col_id): ?string {
    $raw = $defaults[$col_id] ?? null;
    if ($data_type === 'boolean') {
        return isset($defaults[$col_id]) ? '1' : '0';
    }
    if ($data_type === 'number') {
        if ($raw !== null && $raw !== '' && (filter_var($raw, FILTER_VALIDATE_INT) !== false || filter_var($raw, FILTER_VALIDATE_FLOAT) !== false)) {
            return (string)$raw;
        }
        return '0';
    }
    return null;
}

/**
 * Pre-fill the cells of a member who joins a team later, in the team's member lists:
 * - lists from today on and without a date: the list's default (list_global_columns.default_value)
 * - past lists, and lists created before defaults were stored: numbers 0 as before
 *   (no "Ja" for trainings before someone joined — that would distort the statistics)
 */
function prefill_member_cells(PDO $pdo, int $team_id, int $user_id): void {
    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $stmt = $pdo->prepare(
        "INSERT INTO cells (list_id, column_id, member_id, value)
         SELECT list_id, column_id, ?, value FROM (
             SELECT lgc.list_id, lgc.column_id,
                    CASE
                        WHEN (l.date IS NULL OR l.date >= ?) AND lgc.default_value IS NOT NULL THEN lgc.default_value
                        WHEN c.data_type = 'number' THEN '0'
                    END AS value
             FROM list_global_columns lgc
             JOIN columns c ON c.id = lgc.column_id
                 AND c.list_id IS NULL
                 AND c.is_active = TRUE
                 AND (c.team_id = ? OR c.is_system = TRUE)
             JOIN lists l ON l.id = lgc.list_id
                 AND l.team_id = ?
                 AND l.list_type = 'member'
         ) v
         WHERE value IS NOT NULL
         ON CONFLICT (list_id, column_id, member_id) DO NOTHING"
    );
    $stmt->execute([$user_id, $today, $team_id, $team_id]);
}

/**
 * Turn an https ICS feed URL into a webcal:// one.
 *
 * Calendar apps treat webcal:// as "subscribe and keep polling", while an https link to a
 * .ics file is a one-off import that never updates.
 */
function webcal_url(string $url): string {
    return preg_replace('#^https?://#', 'webcal://', $url);
}

/** Series of list dates: the repeat options of the list form. */
const LIST_SERIES_REPEATS = [
    'weekly'    => ['label' => 'Wöchentlich',     'months' => 0],
    'monthly'   => ['label' => 'Monatlich',       'months' => 1],
    'quarterly' => ['label' => 'Vierteljährlich', 'months' => 3],
    'yearly'    => ['label' => 'Jährlich',        'months' => 12],
];
const LIST_SERIES_MAX = 52;
const LIST_CREATE_LOCAL_COLUMNS = 5;   // Zeilen für eigene Spalten im Anlegen-Formular

/**
 * Dates of a list series, starting with $start (Y-m-d). Monthly steps keep the start day and
 * fall back to the last day of shorter months (31.01. → 28.02. → 31.03.).
 * @return list<string> Y-m-d
 */
function list_series_dates(string $start, string $repeat, int $count): array {
    $first = new DateTimeImmutable($start);
    $dates = [];
    for ($k = 0; $k < $count; $k++) {
        if ($repeat === 'weekly') {
            $dates[] = $first->modify('+' . (7 * $k) . ' days')->format('Y-m-d');
            continue;
        }
        $month = $first->modify('first day of this month')->modify('+' . ($k * LIST_SERIES_REPEATS[$repeat]['months']) . ' months');
        $day   = min((int)$first->format('j'), (int)$month->format('t'));
        $dates[] = $month->setDate((int)$month->format('Y'), (int)$month->format('n'), $day)->format('Y-m-d');
    }
    return $dates;
}

/**
 * Absolute URL of a path on this installation, for links shared outside the app.
 * Uses BASE_URL when configured, otherwise the current request's scheme and host.
 */
function absolute_url(string $path): string {
    if (defined('BASE_URL') && BASE_URL !== '') {
        return app_url($path);
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . ltrim($path, '/');
}

/**
 * Dates of a list series from $start up to and including $until. Returns at most
 * LIST_SERIES_MAX + 1 dates, so callers can tell "too many" apart from "exactly the maximum".
 * @return list<string> Y-m-d
 */
function list_series_dates_until(string $start, string $repeat, string $until): array {
    $dates = [];
    for ($n = 1; $n <= LIST_SERIES_MAX + 1; $n++) {
        $all  = list_series_dates($start, $repeat, $n);
        $last = end($all);
        if ($last > $until) break;
        $dates = $all;
    }
    return $dates;
}

/** Return target after creating content: one of the list views, else the overview. */
function coordinator_lists_return_to(?string $url): string {
    $url = (string)$url;
    return preg_match('#^/coordinator/lists(\?[A-Za-z0-9_=&%.-]*)?$#', $url) ? $url : '/coordinator/lists';
}

