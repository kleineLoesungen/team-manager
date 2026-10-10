<?php
// src/member/contents_handler.php — GET /member/contents — overview for member

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

$success = match (true) {
    !empty($_GET['deleted']) => 'Termin gelöscht.',
    !empty($_GET['success']) => 'Gespeichert.',
    default                  => '',
};
$error     = (string)($_GET['error'] ?? '');
$conflicts = max(0, (int)($_GET['conflicts'] ?? 0));

// Termine anlegen, wenn das Team es erlaubt (Koordinatoren-Einstellung)
require_once ROOT_PATH . '/src/db/events.php';
$can_create_events = events_members_may_create($pdo, (int)$_SESSION['team_id']);

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

// Persönlicher Kalender über alle Teams der Person (src/db/calendar.php), nur im Kalender-Tab
$ics_url = null;
if ($showCalendar) {
    require_once ROOT_PATH . '/src/db/calendar.php';
    $ics_url = member_calendar_url($pdo, (int)$_SESSION['user_id']);
}

render_member_page('Inhalte', 'contents', function() use ($items, $success, $error, $conflicts, $can_create_events, $view, $showCalendar, $periodView, $offset, $boundaries, $month, $ics_url, $dashboard) {
    if ($error !== '') render_flash('error', $error);
    if ($success) render_flash('success', $success);
    if ($conflicts) render_resource_conflict_notice($conflicts, '/member/resources');
    require ROOT_PATH . '/src/templates/member/contents.php';
});
