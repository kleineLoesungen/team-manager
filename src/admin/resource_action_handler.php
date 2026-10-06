<?php
// src/admin/resource_action_handler.php — POST: deactivate or reactivate a resource
// $_REQUEST['resource_id'] and $_REQUEST['action'] set by router.
// Deaktiviert: nicht mehr auswählbar, nicht in der Auslastung; bestehende Belegungen bleiben
// gespeichert und sind nach dem Reaktivieren wieder da.

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/resources');
require_csrf();

$resource_id = (int)($_REQUEST['resource_id'] ?? 0);
$active      = ($_REQUEST['action'] ?? '') === 'reactivate';

get_db()->prepare("UPDATE resources SET is_active = ? WHERE id = ?")
        ->execute([$active ? 'true' : 'false', $resource_id]);

redirect('/admin/resources');
