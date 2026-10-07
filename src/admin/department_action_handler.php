<?php
// src/admin/department_action_handler.php — POST: deactivate or reactivate a department
// $_REQUEST['department_id'] and $_REQUEST['action'] set by router.
// Deaktiviert: nicht mehr auswählbar für neue Teams und Ressourcen. Bestehende Teams und
// Ressourcen behalten ihre Abteilung und arbeiten weiter.

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/departments');
require_csrf();

$department_id = (int)($_REQUEST['department_id'] ?? 0);
$active        = ($_REQUEST['action'] ?? '') === 'reactivate';

get_db()->prepare("UPDATE departments SET is_active = ? WHERE id = ?")
        ->execute([$active ? 'true' : 'false', $department_id]);

redirect('/admin/departments');
