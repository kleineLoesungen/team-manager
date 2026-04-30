<?php
// src/player/list_detail_handler.php — GET /player/lists/{id} — list table for player (D-14, CELL-04)
// Shows ALL players as rows (CELL-04). Only own row has edit button.
// Access denied for non-public lists (CELL-03).

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/visibility.php';
require_player();

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();

// Visibility check: player can only see public lists
if (!can_view_list($list_id)) {
    http_response_code(404);
    echo '<h1>Liste nicht gefunden</h1>';
    exit;
}

// Fetch list metadata
$list_stmt = $pdo->prepare("SELECT id, name, visibility FROM lists WHERE id = ?");
$list_stmt->execute([$list_id]);
$list = $list_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch columns (global first, then local) — same query as coach side
$col_stmt = $pdo->prepare(
    "SELECT id, name, data_type, list_id
     FROM columns
     WHERE (list_id = ? OR (list_id IS NULL AND team_id = ?))
       AND is_active = TRUE
     ORDER BY (list_id IS NULL) DESC, sort_order, created_at"
);
$col_stmt->execute([$list_id, $_SESSION['team_id']]);
$columns = $col_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch ALL active players — player sees all rows per CELL-04
$player_stmt = $pdo->prepare(
    "SELECT id, first_name, last_name
     FROM users
     WHERE team_id = ? AND role = 'player' AND is_active = TRUE
     ORDER BY last_name, first_name"
);
$player_stmt->execute([$_SESSION['team_id']]);
$players = $player_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all cells for this list
$cell_stmt = $pdo->prepare(
    "SELECT player_id, column_id, value FROM cells WHERE list_id = ?"
);
$cell_stmt->execute([$list_id]);
$cells = [];
foreach ($cell_stmt->fetchAll(PDO::FETCH_ASSOC) as $cell) {
    $cells[(int)$cell['player_id']][(int)$cell['column_id']] = $cell['value'];
}

$success = !empty($_GET['success']) ? 'Gespeichert.' : '';

// Pass current user_id to template for edit button logic
$current_user_id = (int)$_SESSION['user_id'];

require ROOT_PATH . '/src/templates/player/layout.php';

render_player_page(e($list['name']), 'lists', function() use ($list, $columns, $players, $cells, $current_user_id, $success) {
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/player/list_detail.php';
});
