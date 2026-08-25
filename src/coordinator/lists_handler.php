<?php
// src/coordinator/lists_handler.php — GET /coordinator/lists — overview for coordinator

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

$time_col = (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) ? 'time_start, time_end' : 'NULL AS time_start, NULL AS time_end';
$stmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, date, location, {$time_col}, created_at,
            'list' AS type
     FROM lists
     WHERE team_id = ?"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$files = [];
if (defined('DB_HAS_FILES') && DB_HAS_FILES) {
    $fstmt = $pdo->prepare(
        "SELECT id, name, visibility, is_hidden, date, NULL AS location, created_at,
                'file' AS type
         FROM files
         WHERE team_id = ?"
    );
    $fstmt->execute([$_SESSION['team_id']]);
    $files = $fstmt->fetchAll(PDO::FETCH_ASSOC);
}

$items = array_merge($lists, $files);
usort($items, function(array $a, array $b): int {
    $ad = $a['date'];
    $bd = $b['date'];
    if ($ad !== $bd) {
        if ($ad === null) return 1;
        if ($bd === null) return -1;
        $cmp = strcmp($bd, $ad);
        if ($cmp !== 0) return $cmp;
    }
    return strcmp($b['created_at'], $a['created_at']);
});

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? 'Gespeichert.' : '';

// ── Calendar view logic (per D-01 through D-09) ──────────────────────────
$allowed_views = ['calendar', 'week', 'month', 'list'];
$view = in_array($_GET['view'] ?? '', $allowed_views) ? $_GET['view'] : 'calendar';
$showCalendar = ($view !== 'list');
$periodView   = ($view === 'month') ? 'month' : 'week'; // 'calendar' defaults to week
$offset       = max(-120, min(120, (int)($_GET['offset'] ?? 0))); // clamp offset

$datedItems   = [];
$undatedItems = [];
$boundaries   = ['start' => '', 'end' => '', 'label' => ''];
$ics_url      = '';

if ($showCalendar) {
    require_once ROOT_PATH . '/src/utils/calendar.php';
    $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));

    $boundaries = ($periodView === 'month')
        ? getMonthBoundaries($now, $offset)
        : getWeekBoundaries($now, $offset);

    // Filter $items (already fetched, includes all visibility states for coordinator per D-09)
    $datedItems = array_values(array_filter(
        $items,
        fn($i) => $i['date'] !== null
               && $i['date'] >= $boundaries['start']
               && $i['date'] <= $boundaries['end']
    ));
    // Sort dated items ascending by date (existing $items sort is descending)
    usort($datedItems, fn($a, $b) => strcmp($a['date'], $b['date']));

    // Undated items: all items without a date (sorted created_at DESC from existing $items order)
    $undatedItems = array_values(array_filter($items, fn($i) => $i['date'] === null));

    // Build ICS URL for coordinator's team (D-11, D-14)
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $ics_url = $scheme . '://' . $host . '/ics/' . (int)$_SESSION['team_id'] . '.ics?role=coordinator';

    // Load date-type attribute entries (e.g. birthdays) for active members in this team
    $attr_date_stmt = $pdo->prepare(
        "SELECT m.first_name, m.last_name, ma.name AS attr_name, mav.value
         FROM member_attribute_values mav
         JOIN member_attributes ma ON ma.id = mav.attribute_id AND ma.data_type = 'date'
         JOIN members m ON m.id = mav.member_id
         JOIN users u ON u.member_id = m.id AND u.team_id = ? AND u.role = 'member' AND u.is_active = TRUE
         WHERE mav.value != ''
         ORDER BY m.last_name ASC, m.first_name ASC"
    );
    $attr_date_stmt->execute([$_SESSION['team_id']]);
    $current_year = (int)$now->format('Y');
    foreach ($attr_date_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        try {
            $orig  = new DateTime($row['value']);
            $month = (int)$orig->format('n');
            $day   = (int)$orig->format('j');
            // Feb 29 falls back to Feb 28 in non-leap years
            if ($month === 2 && $day === 29 && !checkdate(2, 29, $current_year)) {
                $day = 28;
            }
            $this_year_date = sprintf('%04d-%02d-%02d', $current_year, $month, $day);
        } catch (\Exception $e) {
            continue;
        }
        if ($this_year_date < $boundaries['start'] || $this_year_date > $boundaries['end']) {
            continue;
        }
        $birth_year = (int)$orig->format('Y');
        $datedItems[] = [
            'date'      => $this_year_date,
            'name'      => $row['first_name'] . ' ' . $row['last_name'],
            'attr_name' => $row['attr_name'],
            'age'       => $birth_year < $current_year ? $current_year - $birth_year : null,
            'type'      => 'attr_date',
        ];
    }
    usort($datedItems, fn($a, $b) => strcmp($a['date'], $b['date']));
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Inhalte', 'lists', function() use ($items, $error, $success, $view, $showCalendar, $periodView, $offset, $boundaries, $datedItems, $undatedItems, $ics_url) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/lists.php';
});
