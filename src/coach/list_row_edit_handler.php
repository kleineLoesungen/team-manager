<?php
// src/coach/list_row_edit_handler.php — GET/POST /coach/lists/{id}/rows/{player_id}/edit
// Coach edits all cells for a specific player row. (CELL-02)
// Access: coaches can edit in public and protected lists; blocked from private? No — CELL-03 says
// coaches have full access to private lists. can_edit_cell() returns true for coaches always.

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/visibility.php';
require_coach();

$list_id   = (int)($_REQUEST['list_id']   ?? 0);
$player_id = (int)($_REQUEST['player_id'] ?? 0);
$pdo       = get_db();

// Authorization check — must happen before any query or render
if (!can_edit_cell($list_id, $player_id)) {
    http_response_code(403);
    echo '<h1>Nicht berechtigt</h1>';
    exit;
}

// Verify player belongs to this team
$player_stmt = $pdo->prepare(
    "SELECT id, first_name, last_name
     FROM users
     WHERE id = ? AND team_id = ? AND role = 'player'"
);
$player_stmt->execute([$player_id, $_SESSION['team_id']]);
$player = $player_stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(404);
    echo '<h1>Spieler nicht gefunden</h1>';
    exit;
}

// Fetch list metadata for display
$list_stmt = $pdo->prepare("SELECT id, name, visibility FROM lists WHERE id = ?");
$list_stmt->execute([$list_id]);
$list = $list_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch columns (global first, then local)
$col_stmt = $pdo->prepare(
    "SELECT id, name, data_type, list_id
     FROM columns
     WHERE (list_id = ? OR (list_id IS NULL AND team_id = ?))
       AND is_active = TRUE
     ORDER BY (list_id IS NULL) DESC, sort_order, created_at"
);
$col_stmt->execute([$list_id, $_SESSION['team_id']]);
$columns = $col_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing cell values for this player in this list
$cell_stmt = $pdo->prepare(
    "SELECT column_id, value FROM cells WHERE list_id = ? AND player_id = ?"
);
$cell_stmt->execute([$list_id, $player_id]);
$existing_cells = [];
foreach ($cell_stmt->fetchAll(PDO::FETCH_ASSOC) as $cell) {
    $existing_cells[(int)$cell['column_id']] = $cell['value'];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Re-check authorization on POST (defense-in-depth)
    if (!can_edit_cell($list_id, $player_id)) {
        redirect('/coach/lists/' . $list_id . '?error=' . urlencode('Nicht berechtigt.'));
    }

    try {
        // Process each submitted cell value
        foreach ($columns as $col) {
            $col_id    = (int)$col['id'];
            $data_type = $col['data_type'];
            $raw_value = $_POST['cells'][$col_id] ?? null;

            // Type-specific validation
            $validated_value = null;

            switch ($data_type) {
                case 'boolean':
                    // Checkboxes: present in POST = true, absent = false
                    $validated_value = isset($_POST['cells'][$col_id]) ? '1' : '0';
                    break;

                case 'number':
                    if ($raw_value !== null && $raw_value !== '') {
                        $int_valid   = filter_var($raw_value, FILTER_VALIDATE_INT)   !== false;
                        $float_valid = filter_var($raw_value, FILTER_VALIDATE_FLOAT) !== false;
                        if ($int_valid || $float_valid) {
                            $validated_value = $raw_value;
                        }
                        // Invalid number input: skip (leave cell unchanged or keep as null)
                    }
                    // Empty string: set to null (clear the cell)
                    if ($raw_value === '') {
                        $validated_value = null;
                    }
                    break;

                case 'text':
                    if ($raw_value !== null) {
                        $validated_value = mb_substr($raw_value, 0, 255); // Truncate to max length
                    }
                    break;
            }

            // UPSERT cell — always save (even null clears the value)
            $upsert = $pdo->prepare(
                "INSERT INTO cells (list_id, column_id, player_id, value)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT (list_id, column_id, player_id)
                 DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
            );
            $upsert->execute([$list_id, $col_id, $player_id, $validated_value]);
        }

        redirect('/coach/lists/' . $list_id . '?success=1');

    } catch (PDOException $e) {
        error_log('Row edit error: ' . $e->getMessage());
        $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
    }
}

require ROOT_PATH . '/src/templates/coach/layout.php';

render_coach_page('Zeile bearbeiten', 'lists', function() use ($list, $player, $columns, $existing_cells, $error) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/coach/list_row_form.php';
});
