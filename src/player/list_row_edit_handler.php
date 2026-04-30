<?php
// src/player/list_row_edit_handler.php — GET/POST /player/lists/{id}/rows/{player_id}/edit
// Player edits only their own row in public lists only. (CELL-01, D-15)
// Server-side ownership check: $_SESSION['user_id'] === $player_id (D-15)

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/visibility.php';
require_player();

$list_id   = (int)($_REQUEST['list_id']   ?? 0);
$player_id = (int)($_REQUEST['player_id'] ?? 0);
$pdo       = get_db();

// CELL-01 + D-15: player can only edit their own row
// can_edit_cell() for players checks: visibility='public' AND $_SESSION['user_id'] === $player_id
if (!can_edit_cell($list_id, $player_id)) {
    http_response_code(403);
    echo '<h1>Nicht berechtigt</h1>';
    exit;
}

// Additional defense: explicit session ownership check (D-15)
if ((int)$_SESSION['user_id'] !== $player_id) {
    http_response_code(403);
    echo '<h1>Nicht berechtigt</h1>';
    exit;
}

// Fetch player info for display
$player_stmt = $pdo->prepare(
    "SELECT id, first_name, last_name
     FROM users
     WHERE id = ? AND team_id = ? AND role = 'player' AND is_active = TRUE"
);
$player_stmt->execute([$player_id, $_SESSION['team_id']]);
$player = $player_stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(404);
    echo '<h1>Spieler nicht gefunden</h1>';
    exit;
}

// Fetch list metadata
$list_stmt = $pdo->prepare("SELECT id, name, visibility FROM lists WHERE id = ? AND visibility = 'public'");
$list_stmt->execute([$list_id]);
$list = $list_stmt->fetch(PDO::FETCH_ASSOC);

if (!$list) {
    // List not found or not public — player cannot access
    http_response_code(404);
    echo '<h1>Liste nicht gefunden</h1>';
    exit;
}

// Fetch columns
$col_stmt = $pdo->prepare(
    "SELECT id, name, data_type, list_id
     FROM columns
     WHERE (list_id = ? OR (list_id IS NULL AND team_id = ?))
       AND is_active = TRUE
     ORDER BY (list_id IS NULL) DESC, sort_order, created_at"
);
$col_stmt->execute([$list_id, $_SESSION['team_id']]);
$columns = $col_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing cell values for this player
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
    if (!can_edit_cell($list_id, $player_id) || (int)$_SESSION['user_id'] !== $player_id) {
        redirect('/player/lists/' . $list_id . '?error=' . urlencode('Nicht berechtigt.'));
    }

    try {
        foreach ($columns as $col) {
            $col_id    = (int)$col['id'];
            $data_type = $col['data_type'];
            $raw_value = $_POST['cells'][$col_id] ?? null;

            $validated_value = null;

            switch ($data_type) {
                case 'boolean':
                    $validated_value = isset($_POST['cells'][$col_id]) ? '1' : '0';
                    break;

                case 'number':
                    if ($raw_value !== null && $raw_value !== '') {
                        $int_valid   = filter_var($raw_value, FILTER_VALIDATE_INT)   !== false;
                        $float_valid = filter_var($raw_value, FILTER_VALIDATE_FLOAT) !== false;
                        if ($int_valid || $float_valid) {
                            $validated_value = $raw_value;
                        }
                    }
                    if ($raw_value === '') {
                        $validated_value = null;
                    }
                    break;

                case 'text':
                    if ($raw_value !== null) {
                        $validated_value = mb_substr($raw_value, 0, 255);
                    }
                    break;
            }

            $upsert = $pdo->prepare(
                "INSERT INTO cells (list_id, column_id, player_id, value)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT (list_id, column_id, player_id)
                 DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
            );
            $upsert->execute([$list_id, $col_id, $player_id, $validated_value]);
        }

        redirect('/player/lists/' . $list_id . '?success=1');

    } catch (PDOException $e) {
        error_log('Player row edit error: ' . $e->getMessage());
        $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
    }
}

require ROOT_PATH . '/src/templates/player/layout.php';

render_player_page('Zeile bearbeiten', 'lists', function() use ($list, $player, $columns, $existing_cells, $error) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/player/list_row_form.php';
});
