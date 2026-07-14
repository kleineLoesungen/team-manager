<?php
// src/member/lists_handler.php — GET /member/lists — overview for member

declare(strict_types=1);

require_member();

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, date, location, created_at,
            'list' AS type
     FROM lists
     WHERE team_id = ? AND visibility IN ('public', 'protected')"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$files = [];
if (defined('DB_HAS_FILES') && DB_HAS_FILES) {
    $fstmt = $pdo->prepare(
        "SELECT id, name, visibility, is_hidden, date, NULL AS location, created_at,
                'file' AS type
         FROM files
         WHERE team_id = ? AND visibility IN ('public', 'protected')"
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

$success = !empty($_GET['success']) ? 'Gespeichert.' : '';

// ── Calendar view logic (per D-08, D-04, D-05) ───────────────────────────
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

    // Filter $items — visibility already restricted to public+protected by SQL (D-08)
    $datedItems = array_values(array_filter(
        $items,
        fn($i) => $i['date'] !== null
               && $i['date'] >= $boundaries['start']
               && $i['date'] <= $boundaries['end']
    ));
    // Sort dated items ascending by date (existing $items sort is descending)
    usort($datedItems, fn($a, $b) => strcmp($a['date'], $b['date']));

    // Undated items: all items without a date
    $undatedItems = array_values(array_filter($items, fn($i) => $i['date'] === null));

    // ICS URL for member's team (D-11, D-14)
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $ics_url = $scheme . '://' . $host . '/ics/' . (int)$_SESSION['team_id'] . '.ics';
}

require ROOT_PATH . '/src/templates/member/layout.php';

render_player_page('Inhalte', 'lists', function() use ($items, $success, $view, $showCalendar, $periodView, $offset, $boundaries, $datedItems, $undatedItems, $ics_url) {
    if ($success) echo '<div class="alert alert-success">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/member/lists.php';
});
