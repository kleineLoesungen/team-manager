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
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO columns (team_id, list_id, name, data_type, is_system, sort_order)
             VALUES (NULL, NULL, ?, ?, TRUE, ?) RETURNING id"
        );
        $stmt->execute([$name, $data_type, $sort_order]);
        $new_id = (int)$stmt->fetchColumn();

        // Promote any matching team global columns (same name + type) to this new system column
        $conflict_stmt = $pdo->prepare(
            "SELECT id FROM columns
             WHERE name = ? AND data_type = ? AND is_system = FALSE AND list_id IS NULL"
        );
        $conflict_stmt->execute([$name, $data_type]);
        $old_ids = $conflict_stmt->fetchAll(PDO::FETCH_COLUMN);
        $promoted = count($old_ids);

        foreach ($old_ids as $old_id) {
            $old_id = (int)$old_id;

            // Remap list_global_columns: old team column → new system column (skip conflicts)
            $pdo->prepare(
                "UPDATE list_global_columns SET column_id = ?
                 WHERE column_id = ?
                   AND NOT EXISTS (
                       SELECT 1 FROM list_global_columns ex
                       WHERE ex.list_id = list_global_columns.list_id AND ex.column_id = ?
                   )"
            )->execute([$new_id, $old_id, $new_id]);
            $pdo->prepare("DELETE FROM list_global_columns WHERE column_id = ?")->execute([$old_id]);

            // Remap cells: old team column → new system column (skip conflicts)
            $pdo->prepare(
                "UPDATE cells SET column_id = ?
                 WHERE column_id = ?
                   AND NOT EXISTS (
                       SELECT 1 FROM cells ex
                       WHERE ex.list_id = cells.list_id
                         AND ex.column_id = ?
                         AND ex.member_id = cells.member_id
                   )"
            )->execute([$new_id, $old_id, $new_id]);
            $pdo->prepare("DELETE FROM cells WHERE column_id = ?")->execute([$old_id]);

            $pdo->prepare("DELETE FROM columns WHERE id = ? AND is_system = FALSE")->execute([$old_id]);
        }

        $pdo->commit();
        $qs = $promoted > 0 ? '?success=1&promoted=' . $promoted : '?success=1';
        redirect('/admin/columns' . $qs);
    } catch (PDOException $e) {
        $pdo->rollBack();
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
