<?php
// src/coordinator/resource_check_handler.php — GET /coordinator/resources/check
// Prüfung vor dem Speichern (Formulare für Listen und Termine, Skript in render_resource_picker):
// liefert den Hinweis auf Überschneidungen als HTML-Ausschnitt, leer wenn frei.
// Parameter wie im Formular: resource_ids[], date, time_start, time_end, is_all_day,
// repeat + repeat_until (Serie), exclude = "list:12" | "event:3" (der bearbeitete Eintrag).

declare(strict_types=1);

require_coordinator();
require_once ROOT_PATH . '/src/db/resources.php';
require_once ROOT_PATH . '/src/templates/components/partials.php';

$date  = (string)($_GET['date'] ?? '');
$dates = [$date];
$repeat = (string)($_GET['repeat'] ?? '');
$until  = (string)($_GET['repeat_until'] ?? '');
if (array_key_exists($repeat, LIST_SERIES_REPEATS) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) && $until >= $date) {
    $dates = list_series_dates_until($date, $repeat, $until) ?: [$date];
}

[$exclude_kind, $exclude_id] = array_pad(explode(':', (string)($_GET['exclude'] ?? ''), 2), 2, '');

$conflicts = resources_check(
    get_db(),
    (array)($_GET['resource_ids'] ?? []),
    $dates,
    (string)($_GET['time_start'] ?? ''),
    (string)($_GET['time_end'] ?? ''),
    !empty($_GET['is_all_day']),
    in_array($exclude_kind, ['list', 'event'], true) ? $exclude_kind : null,
    (int)$exclude_id
);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
render_resource_conflicts($conflicts, 'Speichern ist trotzdem möglich.');
