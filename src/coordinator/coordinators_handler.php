<?php
// src/coordinator/coordinators_handler.php — GET /coordinator/coordinators — read-only coordinator directory

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

// RLS is set to current team context by require_coordinator().
// Explicit team_id filter for clarity; is_active filters out deactivated accounts.
$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, phone
     FROM users
     WHERE role = 'coordinator'
       AND team_id = ?
       AND is_active = TRUE
     ORDER BY last_name ASC, first_name ASC"
);
$stmt->execute([(int)$_SESSION['team_id']]);
$coordinators = $stmt->fetchAll();

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Koordinatoren', 'coordinators', function() use ($coordinators) {
    require ROOT_PATH . '/src/templates/coordinator/coordinators.php';
});
