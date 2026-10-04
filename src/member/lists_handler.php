<?php
// src/member/lists_handler.php — GET /member/lists — overview for member

declare(strict_types=1);

require_member();

$pdo = get_db();

$time_col = 'time_start, time_end';
$stmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, date, location, {$time_col}, created_at,
            'list' AS type
     FROM lists
     WHERE team_id = ? AND visibility IN ('public', 'protected')"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fstmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, date, NULL AS location, created_at,
            'file' AS type
     FROM files
     WHERE team_id = ? AND visibility IN ('public', 'protected')"
);
$fstmt->execute([$_SESSION['team_id']]);
$files = $fstmt->fetchAll(PDO::FETCH_ASSOC);

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
// Ansichten: Übersicht (Standard, ersetzt die frühere Wochenansicht) | Monat | Liste.
// Alte Links (?view=calendar / ?view=week) landen in der Übersicht.
$allowed_views = ['overview', 'month', 'list'];
$view = in_array($_GET['view'] ?? '', $allowed_views, true) ? $_GET['view'] : 'overview';
$showCalendar = ($view === 'month');
$periodView   = 'month';
$offset       = max(-120, min(120, (int)($_GET['offset'] ?? 0))); // clamp offset

$month      = null;   // Monatsansicht: gleiche Zeilen wie die Übersicht (src/db/dashboard.php)
$boundaries = ['start' => '', 'end' => '', 'label' => ''];
if ($showCalendar) {
    require_once ROOT_PATH . '/src/utils/calendar.php';
    require_once ROOT_PATH . '/src/db/dashboard.php';
    $now        = new DateTime('now', new DateTimeZone('Europe/Berlin'));
    $boundaries = getMonthBoundaries($now, $offset);
    $month      = dashboard_month_data($pdo, 'member', $boundaries);
}

// Übersicht: Live, nächste 7 Tage, eigene Werte (src/db/dashboard.php)
$dashboard = null;
if ($view === 'overview') {
    require_once ROOT_PATH . '/src/db/dashboard.php';
    $dashboard = dashboard_data($pdo, 'member');
}

require ROOT_PATH . '/src/templates/member/layout.php';

$tstmt = $pdo->prepare("SELECT calendar_token_member FROM teams WHERE id = ?");
$tstmt->execute([$_SESSION['team_id']]);
$cal_token = $tstmt->fetchColumn();
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ics_url   = $cal_token ? ($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/ics/' . $cal_token . '.ics') : null;

render_member_page('Inhalte', 'lists', function() use ($items, $success, $view, $showCalendar, $periodView, $offset, $boundaries, $month, $ics_url, $dashboard) {
    if ($success) echo '<div class="alert alert-success">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/member/lists.php';
});
