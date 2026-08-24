<?php
// src/coach/columns_handler.php — GET /coach/columns — global columns overview (LIST-02)

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

// System columns are team_id = NULL — need admin context to bypass RLS
set_admin_context($pdo);
$sys_stmt = $pdo->prepare(
    "SELECT id, name, data_type, sort_order
     FROM columns
     WHERE is_system = TRUE AND list_id IS NULL
     ORDER BY sort_order ASC, name ASC"
);
$sys_stmt->execute();
$system_columns = $sys_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch global columns for this team (list_id IS NULL = global, is_system = FALSE)
$stmt = $pdo->prepare(
    "SELECT id, name, data_type, is_active, created_at
     FROM columns
     WHERE team_id = ? AND list_id IS NULL
     ORDER BY sort_order, created_at"
);
$stmt->execute([$_SESSION['team_id']]);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? 'Spalte angelegt.'  : '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Globale Spalten', 'columns', function() use ($columns, $system_columns, $error, $success) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/columns.php';
});
