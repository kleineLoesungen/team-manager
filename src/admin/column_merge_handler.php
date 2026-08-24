<?php
// src/admin/column_merge_handler.php — GET/POST /admin/columns/{src_id}/merge/{target_id}
// GET: confirmation page showing what will be merged.
// POST: remap all references from src to target, then delete src.

declare(strict_types=1);

require_admin();

$src_id    = isset($matches[1]) ? (int)$matches[1] : 0;
$target_id = isset($matches[2]) ? (int)$matches[2] : 0;

if ($src_id <= 0 || $target_id <= 0 || $src_id === $target_id) {
    redirect('/admin/columns');
}

$pdo = get_db();

$fetch = function(int $id) use ($pdo): array|false {
    $s = $pdo->prepare(
        "SELECT id, name, data_type FROM columns WHERE id = ? AND is_system = TRUE AND list_id IS NULL"
    );
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC);
};

$src    = $fetch($src_id);
$target = $fetch($target_id);

if (!$src || !$target || $src['data_type'] !== $target['data_type']) {
    redirect('/admin/columns');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    try {
        // Remap list_global_columns: src → target (skip conflicts — target already has the list)
        $pdo->prepare(
            "UPDATE list_global_columns SET column_id = ?
             WHERE column_id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM list_global_columns ex
                   WHERE ex.list_id = list_global_columns.list_id AND ex.column_id = ?
               )"
        )->execute([$target_id, $src_id, $target_id]);

        // Remove any remaining list_global_columns for src (conflict rows)
        $pdo->prepare("DELETE FROM list_global_columns WHERE column_id = ?")->execute([$src_id]);

        // Remap cells: src → target (skip conflicts — same list+player already has target cell)
        $pdo->prepare(
            "UPDATE cells SET column_id = ?
             WHERE column_id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM cells ex
                   WHERE ex.list_id = cells.list_id
                     AND ex.column_id = ?
                     AND ex.member_id = cells.member_id
               )"
        )->execute([$target_id, $src_id, $target_id]);

        // Remove any remaining cells for src (conflict rows)
        $pdo->prepare("DELETE FROM cells WHERE column_id = ?")->execute([$src_id]);

        // Delete source column
        $pdo->prepare("DELETE FROM columns WHERE id = ? AND is_system = TRUE")->execute([$src_id]);

        redirect('/admin/columns?merged=1');
    } catch (PDOException $e) {
        error_log('Column merge error: ' . $e->getMessage());
        redirect('/admin/columns/' . $src_id . '/merge/' . $target_id . '?error=1');
    }
}

// GET: load counts for the confirmation page
$count_cells = function(int $id) use ($pdo): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM cells WHERE column_id = ?");
    $s->execute([$id]);
    return (int)$s->fetchColumn();
};
$count_lists = function(int $id) use ($pdo): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM list_global_columns WHERE column_id = ?");
    $s->execute([$id]);
    return (int)$s->fetchColumn();
};

$src_cells  = $count_cells($src_id);
$tgt_cells  = $count_cells($target_id);
$src_lists  = $count_lists($src_id);
$tgt_lists  = $count_lists($target_id);
$error      = !empty($_GET['error']);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Spalten zusammenführen', 'settings', function() use ($src, $target, $src_cells, $tgt_cells, $src_lists, $tgt_lists, $error) {
    require ROOT_PATH . '/src/templates/admin/column_merge_confirm.php';
});
