<?php
// src/public/guest_events_handler.php — GET /guest/events — Termine für Gäste (ohne Anmeldung)
// Einträge „Für Gäste sichtbar“ ab heute, nach Tagen gruppiert, Filter nach Abteilung, dazu die
// Gast-Kalender-Abos der Teams (src/db/guest.php, Issue #15).

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/guest.php';
require_once ROOT_PATH . '/src/db/departments.php';
require_once ROOT_PATH . '/src/db/dashboard.php';

$pdo         = get_db();
$departments = guest_departments($pdo);
$department  = department_filter($departments);
$days        = guest_upcoming($pdo, $department);
$calendars   = guest_calendars($pdo, $department);

require ROOT_PATH . '/src/templates/public/guest_events.php';
