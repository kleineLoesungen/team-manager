<?php
// Redirect: personal data editing moved to /admin/coordinators/{id}/settings
declare(strict_types=1);
require_admin();
$coordinator_id = (int)($_REQUEST['coordinator_id'] ?? 0);
redirect($coordinator_id > 0 ? '/admin/coordinators/' . $coordinator_id . '/settings' : '/admin/coordinators');
