<?php
// src/coach/list_create_handler.php — GET/POST /coach/lists/create (LIST-01, LIST-04)

declare(strict_types=1);

require_coach();

$pdo   = get_db();
$error = '';

// Fetch all active global columns for this team (checkbox display + default value inputs)
$cols_stmt = $pdo->prepare(
    "SELECT id, name, data_type FROM columns
     WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
     ORDER BY sort_order, created_at"
);
$cols_stmt->execute([$_SESSION['team_id']]);
$global_columns = $cols_stmt->fetchAll(PDO::FETCH_ASSOC);

require ROOT_PATH . '/src/templates/coach/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name          = trim($_POST['name'] ?? '');
    $visibility    = $_POST['visibility'] ?? 'public';
    $show_all_rows = isset($_POST['show_all_rows']) ? 1 : 0;
    $selected_cols = array_map('intval', (array)($_POST['global_columns'] ?? []));
    $defaults      = (array)($_POST['defaults'] ?? []);  // [col_id => raw_value]

    if (empty($name)) {
        $error = 'Name ist erforderlich.';
    } elseif (!in_array($visibility, ['public', 'protected', 'private'])) {
        $error = 'Ungültiger Sichtbarkeits-Status.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO lists (team_id, name, visibility, show_all_rows)
                 VALUES (?, ?, ?, ?)
                 RETURNING id"
            );
            $stmt->execute([$_SESSION['team_id'], $name, $visibility, $show_all_rows]);
            $list_id = (int)$stmt->fetchColumn();

            // Link selected global columns (D-11) — validate ownership first
            $valid_ids = [];
            if (!empty($selected_cols)) {
                $placeholders = implode(',', array_fill(0, count($selected_cols), '?'));
                $valid_stmt = $pdo->prepare(
                    "SELECT id FROM columns
                     WHERE id IN ($placeholders) AND team_id = ? AND list_id IS NULL AND is_active = TRUE"
                );
                $valid_stmt->execute([...$selected_cols, $_SESSION['team_id']]);
                $valid_ids = $valid_stmt->fetchAll(PDO::FETCH_COLUMN);

                $link_stmt = $pdo->prepare(
                    "INSERT INTO list_global_columns (list_id, column_id) VALUES (?, ?)"
                );
                foreach ($valid_ids as $col_id) {
                    $link_stmt->execute([$list_id, (int)$col_id]);
                }
            }

            // Pre-populate default cell values for all active players on this team
            if (!empty($valid_ids)) {
                // Fetch column type map for validation
                $type_map = [];
                foreach ($global_columns as $gc) {
                    $type_map[(int)$gc['id']] = $gc['data_type'];
                }

                // Fetch all active players
                $players_stmt = $pdo->prepare(
                    "SELECT id FROM users WHERE team_id = ? AND role = 'player' AND is_active = TRUE"
                );
                $players_stmt->execute([$_SESSION['team_id']]);
                $player_ids = $players_stmt->fetchAll(PDO::FETCH_COLUMN);

                $cell_stmt = $pdo->prepare(
                    "INSERT INTO cells (list_id, column_id, player_id, value)
                     VALUES (?, ?, ?, ?)
                     ON CONFLICT (list_id, column_id, player_id) DO NOTHING"
                );

                foreach ($valid_ids as $col_id) {
                    $col_id     = (int)$col_id;
                    $data_type  = $type_map[$col_id] ?? null;
                    $raw        = $defaults[$col_id] ?? null;

                    // Validate and normalise default value per type
                    $value = null;
                    if ($data_type === 'boolean') {
                        $value = isset($defaults[$col_id]) ? '1' : '0';
                    } elseif ($data_type === 'number' && $raw !== null && $raw !== '') {
                        $int_ok   = filter_var($raw, FILTER_VALIDATE_INT)   !== false;
                        $float_ok = filter_var($raw, FILTER_VALIDATE_FLOAT) !== false;
                        if ($int_ok || $float_ok) {
                            $value = $raw;
                        }
                    }

                    if ($value === null) {
                        continue; // No default set — leave cell empty
                    }

                    foreach ($player_ids as $pid) {
                        $cell_stmt->execute([$list_id, $col_id, (int)$pid, $value]);
                    }
                }
            }

            $pdo->commit();
            redirect('/coach/lists/' . $list_id . '?success=1');

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('List create error: ' . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
    }
}

render_coach_page('Neue Liste anlegen', 'lists', function() use ($error, $global_columns) {
    require ROOT_PATH . '/src/templates/coach/list_form.php';
});
