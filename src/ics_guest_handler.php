<?php
// src/ics_guest_handler.php — GET /ics/guest/all.ics | /ics/guest/department/{id}.ics
// Gast-Kalender ohne Token (Issue #15): alle Einträge „Für Gäste sichtbar“ der Organisation oder
// einer Abteilung — nur Titel, Ort, Datum und Zeit, Teamname und Abteilungs-Symbol davor.
// Der Inhalt ist ohnehin öffentlich (Gastbereich), deshalb ist kein Token nötig.

declare(strict_types=1);

require_once ROOT_PATH . '/src/utils/calendar.php';
require_once ROOT_PATH . '/src/db/guest.php';

$department_id = (int)($_REQUEST['department_id'] ?? 0);
$pdo = get_db();

if ($department_id > 0) {
    $exists = as_admin($pdo, function () use ($pdo, $department_id) {
        $stmt = $pdo->prepare("SELECT 1 FROM departments WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$department_id]);
        return $stmt->fetchColumn();
    });
    if (!$exists) {
        http_response_code(404);
        exit;
    }
    $feed     = guest_calendar_feed($pdo, 'department', $department_id);
    $filename = 'gast-abteilung-' . $department_id . '.ics';
} else {
    $feed     = guest_calendar_feed($pdo, 'all');
    $filename = 'gast-alle.ics';
}

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo ics_team_calendar($feed['lists'], $feed['events'], $base_url, 'member', true, true);
exit;
