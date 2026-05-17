<?php
// src/coach/list_detail_handler.php — GET /coordinator/lists/{id} — list table view (CELL-04)
// Shows all players as rows, all columns (global + local) as table columns.
// Per D-03: global columns first, then local. Per D-05: empty cells show blank.

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/visibility.php';
require_coordinator();

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();

// Verify coach can view this list (team ownership check)
if (!can_view_list($list_id)) {
    http_response_code(404);
    echo '<h1>Liste nicht gefunden</h1>';
    exit;
}

// Fetch list metadata (include list_type if available)
$list_type_col = (defined('DB_HAS_LIST_TYPE') && DB_HAS_LIST_TYPE) ? ", list_type" : "";
$list_stmt = $pdo->prepare("SELECT id, name, visibility, date, description{$list_type_col} FROM lists WHERE id = ?");
$list_stmt->execute([$list_id]);
$list = $list_stmt->fetch(PDO::FETCH_ASSOC);

// Normalize: if list_type not in DB yet, default to 'member'
if (!isset($list['list_type'])) {
    $list['list_type'] = 'member';
}

$is_free_list = ($list['list_type'] === 'free');
$free_rows    = [];

if ($is_free_list) {
    // Free list: fetch custom rows instead of team members
    $fr_stmt = $pdo->prepare(
        "SELECT id, label, position FROM free_list_rows WHERE list_id = ? ORDER BY position, created_at"
    );
    $fr_stmt->execute([$list_id]);
    $free_rows = $fr_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Local columns only (no global columns for free lists)
    $col_stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.data_type, c.list_id, " . (DB_HAS_COACH_ONLY ? 'c.coach_only' : 'FALSE AS coach_only') . "
         FROM columns c
         WHERE c.is_active = TRUE AND c.list_id = ?
         ORDER BY c.sort_order, c.created_at"
    );
    $col_stmt->execute([$list_id]);
    $columns = $col_stmt->fetchAll(PDO::FETCH_ASSOC);

    $players = []; // Not used in free list path
} else {
    // Member list: existing behaviour
    $col_stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.data_type, c.list_id, " . (DB_HAS_COACH_ONLY ? 'c.coach_only' : 'FALSE AS coach_only') . "
         FROM columns c
         WHERE c.is_active = TRUE
           AND (
             c.list_id = ?
             OR (c.list_id IS NULL AND c.team_id = ?
                 AND EXISTS (
                   SELECT 1 FROM list_global_columns lgc
                   WHERE lgc.list_id = ? AND lgc.column_id = c.id
                 ))
           )
         ORDER BY (c.list_id IS NULL) DESC, c.sort_order, c.created_at"
    );
    $col_stmt->execute([$list_id, $_SESSION['team_id'], $list_id]);
    $columns = $col_stmt->fetchAll(PDO::FETCH_ASSOC);

    $player_stmt = $pdo->prepare(
        "SELECT id, first_name, last_name
         FROM users
         WHERE team_id = ? AND role = 'member' AND is_active = TRUE
         ORDER BY first_name, last_name"
    );
    $player_stmt->execute([$_SESSION['team_id']]);
    $players = $player_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all cell values for this list and build map [row_id][column_id] => value
$cell_stmt = $pdo->prepare(
    "SELECT player_id, column_id, value FROM cells WHERE list_id = ?"
);
$cell_stmt->execute([$list_id]);
$cells = [];
foreach ($cell_stmt->fetchAll(PDO::FETCH_ASSOC) as $cell) {
    $cells[(int)$cell['player_id']][(int)$cell['column_id']] = $cell['value'];
}

$post_error     = '';
$confirm_delete = null; // Set when showing delete confirmation for a free row

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? 'save_cells';

    if ($is_free_list && $action === 'add_row') {
        // Add a custom row to the free list
        $label    = trim($_POST['row_label'] ?? '');
        $position = (int)($_POST['row_position'] ?? 0);

        if ($label === '') {
            $post_error = 'Zeilenbezeichnung darf nicht leer sein.';
        } else {
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO free_list_rows (list_id, label, position) VALUES (?, ?, ?)"
                );
                $ins->execute([$list_id, $label, $position]);
                redirect('/coordinator/lists/' . $list_id . '?success=1');
            } catch (PDOException $e) {
                error_log('Free list add_row error: ' . $e->getMessage());
                $post_error = 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
            }
        }

    } elseif ($is_free_list && $action === 'delete_row') {
        $row_id  = (int)($_POST['row_id'] ?? 0);
        $confirm = $_POST['confirm'] ?? '';

        // Verify row belongs to this list
        $chk = $pdo->prepare("SELECT id, label FROM free_list_rows WHERE id = ? AND list_id = ?");
        $chk->execute([$row_id, $list_id]);
        $row_to_delete = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row_to_delete) {
            $post_error = 'Zeile nicht gefunden.';
        } elseif ($confirm !== '1') {
            // Show confirmation — re-render with confirmation prompt
            $confirm_delete = $row_to_delete;
            // Reload free_rows for render (POST may have changed nothing yet)
            $fr_stmt2 = $pdo->prepare(
                "SELECT id, label, position FROM free_list_rows WHERE list_id = ? ORDER BY position, created_at"
            );
            $fr_stmt2->execute([$list_id]);
            $free_rows = $fr_stmt2->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Confirmed — delete cells then row
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM cells WHERE list_id = ? AND player_id = ?")
                    ->execute([$list_id, $row_id]);
                $pdo->prepare("DELETE FROM free_list_rows WHERE id = ? AND list_id = ?")
                    ->execute([$row_id, $list_id]);
                $pdo->commit();
                redirect('/coordinator/lists/' . $list_id . '?success=1');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Free list delete_row error: ' . $e->getMessage());
                $post_error = 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
            }
        }

    } elseif ($action === 'save_description') {
        $new_description = trim($_POST['description'] ?? '');
        try {
            $upd = $pdo->prepare(
                "UPDATE lists SET description = ?, updated_at = NOW() WHERE id = ? AND team_id = ?"
            );
            $upd->execute([
                $new_description !== '' ? $new_description : null,
                $list_id,
                $_SESSION['team_id'],
            ]);
            redirect('/coordinator/lists/' . $list_id . '?success=1');
        } catch (PDOException $e) {
            error_log('List description save error: ' . $e->getMessage());
            $post_error = 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
        }

    } else {
        // save_cells — works for both member and free lists
        try {
            $submitted = $_POST['cells'] ?? [];

            $rows_to_save = $is_free_list ? $free_rows : $players;
            $row_id_key   = $is_free_list ? 'id' : 'id';

            foreach ($rows_to_save as $row) {
                $rid = (int)$row['id'];
                foreach ($columns as $col) {
                    $col_id    = (int)$col['id'];
                    $data_type = $col['data_type'];
                    $raw_value = $submitted[$rid][$col_id] ?? null;

                    switch ($data_type) {
                        case 'boolean':
                            $validated_value = isset($submitted[$rid][$col_id]) ? '1' : '0';
                            break;
                        case 'number':
                            if ($raw_value !== null && $raw_value !== '') {
                                $int_valid   = filter_var($raw_value, FILTER_VALIDATE_INT)   !== false;
                                $float_valid = filter_var($raw_value, FILTER_VALIDATE_FLOAT) !== false;
                                $validated_value = ($int_valid || $float_valid) ? $raw_value : null;
                            } else {
                                $validated_value = null;
                            }
                            break;
                        case 'text':
                        default:
                            $validated_value = ($raw_value !== null) ? mb_substr($raw_value, 0, 255) : null;
                            break;
                    }

                    $upsert = $pdo->prepare(
                        "INSERT INTO cells (list_id, column_id, player_id, value)
                         VALUES (?, ?, ?, ?)
                         ON CONFLICT (list_id, column_id, player_id)
                         DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
                    );
                    $upsert->execute([$list_id, $col_id, $rid, $validated_value]);
                }
            }

            redirect('/coordinator/lists/' . $list_id . '?success=1');

        } catch (PDOException $e) {
            error_log('Bulk cell save error: ' . $e->getMessage());
            $post_error = 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
        }
    }
}

$error   = $post_error !== '' ? $post_error : (!empty($_GET['error']) ? e($_GET['error']) : '');
$success = !empty($_GET['success']) ? 'Gespeichert.' : '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page(e($list['name']), 'lists', function() use ($list, $columns, $players, $cells, $error, $success, $is_free_list, $free_rows, $confirm_delete) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/list_detail.php';
});
