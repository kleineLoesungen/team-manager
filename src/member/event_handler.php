<?php
// src/member/event_handler.php — GET /member/events/{id} — Termin ansehen
// Mitglieder sehen Termine für das Team (protected), RLS lässt private nicht durch.
// Eigene Termine lassen sich bearbeiten, solange das Team Mitglieder-Termine erlaubt.

declare(strict_types=1);

require_member();
require_once ROOT_PATH . '/src/db/events.php';

$event_id = (int)($_REQUEST['event_id'] ?? 0);
$pdo      = get_db();
$event    = event_load($pdo, $event_id, (int)$_SESSION['team_id']);
if (!$event) redirect('/member/lists');

$can_edit       = event_member_may_edit($pdo, $event, (int)$_SESSION['user_id']);
$resource_names = resources_booked_names($pdo, 'event', $event_id);

require ROOT_PATH . '/src/templates/member/layout.php';

render_member_page(e($event['title']), 'lists', function() use ($event, $can_edit, $resource_names) {
    require ROOT_PATH . '/src/templates/member/event.php';
});
