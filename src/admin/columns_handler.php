<?php
// src/admin/columns_handler.php — GET/POST /admin/columns
// Admin CRUD for system columns (cross-team, immutable by coordinators).
// System columns: team_id = NULL, list_id = NULL, is_system = TRUE.

declare(strict_types=1);

require_admin();

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = trim($_POST['action'] ?? '');

    if ($action !== 'create') {
        redirect('/admin/columns');
    }

    $name      = trim($_POST['name'] ?? '');
    $data_type = trim($_POST['data_type'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        redirect('/admin/columns?error=' . urlencode('Name ist erforderlich (max. 100 Zeichen).'));
    }
    if (!in_array($data_type, ['boolean', 'number'], true)) {
        redirect('/admin/columns?error=' . urlencode('Ungültiger Typ. Systemspalten erlauben nur Ja/Nein oder Zahl.'));
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO columns (team_id, list_id, name, data_type, is_system, sort_order)
             VALUES (NULL, NULL, ?, ?, TRUE, ?)"
        );
        $stmt->execute([$name, $data_type, $sort_order]);
        redirect('/admin/columns?success=1');
    } catch (PDOException $e) {
        error_log('Admin system column create error: ' . $e->getMessage());
        redirect('/admin/columns?error=' . urlencode('Ein Fehler ist aufgetreten.'));
    }
}

// GET: list all system columns
$stmt = $pdo->query(
    "SELECT id, name, data_type, sort_order, created_at FROM columns
     WHERE is_system = TRUE AND list_id IS NULL
     ORDER BY sort_order ASC, name ASC"
);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = !empty($_GET['success']);
$deleted = !empty($_GET['deleted']);
$error   = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Systemspalten', 'settings', function() use ($columns, $success, $deleted, $error) {
    require ROOT_PATH . '/src/templates/admin/columns.php';
});
