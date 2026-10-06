<?php
// src/coordinator/lists_handler.php — GET /coordinator/lists — overview for coordinator

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

$time_col = 'time_start, time_end';
$stmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, date, location, {$time_col}, created_at,
            'list' AS type
     FROM lists
     WHERE team_id = ?"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fstmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, date, NULL AS location, created_at,
            'file' AS type
     FROM files
     WHERE team_id = ?"
);
$fstmt->execute([$_SESSION['team_id']]);
$files = $fstmt->fetchAll(PDO::FETCH_ASSOC);

$estmt = $pdo->prepare(
    "SELECT id, title AS name, visibility, is_hidden, date, is_all_day, time_start, time_end, icon, location, created_at,
            'event' AS type
     FROM events WHERE team_id = ?"
);
$estmt->execute([$_SESSION['team_id']]);
$events = $estmt->fetchAll();

$items = array_merge($lists, $files, $events);
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
// Nach dem Speichern: so viele Listen/Termine teilen sich eine Ressource mit einer anderen Belegung
$conflicts = max(0, (int)($_GET['conflicts'] ?? 0));
$success = match (true) {
    ($_GET['success'] ?? '') === 'series' => max(2, (int)($_GET['count'] ?? 0)) . ' Termine angelegt. Jeder lässt sich einzeln bearbeiten.',
    !empty($_GET['success'])              => 'Gespeichert.',
    default                               => '',
};

// ── Calendar view logic (per D-01 through D-09) ──────────────────────────
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
    $month      = dashboard_month_data($pdo, 'coordinator', $boundaries);
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

$tstmt = $pdo->prepare("SELECT calendar_token_coordinator FROM teams WHERE id = ?");
$tstmt->execute([$_SESSION['team_id']]);
$cal_token = $tstmt->fetchColumn();
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ics_url   = $cal_token ? ($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/ics/' . $cal_token . '.ics') : null;

// Übersicht: Live, nächste 7 Tage, eigene Werte (src/db/dashboard.php)
$dashboard = null;
if ($view === 'overview') {
    require_once ROOT_PATH . '/src/db/dashboard.php';
    $dashboard = dashboard_data($pdo, 'coordinator');
}

render_coach_page('Inhalte', 'lists', function() use ($items, $error, $success, $view, $showCalendar, $periodView, $offset, $boundaries, $month, $ics_url, $dashboard, $conflicts) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($conflicts) {
        echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>'
           . ($conflicts === 1 ? 'Ein Eintrag nutzt' : $conflicts . ' Einträge nutzen')
           . ' eine Ressource, die zur gleichen Zeit schon belegt ist. '
           . '<a href="/coordinator/resources" class="alert-link">Auslastung ansehen</a></div>';
    }
    require ROOT_PATH . '/src/templates/coordinator/lists.php';
});
