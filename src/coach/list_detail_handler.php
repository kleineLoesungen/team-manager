<?php
// src/coach/list_detail_handler.php — GET /coach/lists/{id} — list table view (CELL-04)
// Shows all players as rows, all columns (global + local) as table columns.
// Per D-03: global columns first, then local. Per D-05: empty cells show blank.

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/visibility.php';
require_coach();

$list_id = (int)($_REQUEST['list_id'] ?? 0);
$pdo     = get_db();

// Verify coach can view this list (team ownership check)
if (!can_view_list($list_id)) {
    http_response_code(404);
    echo '<h1>Liste nicht gefunden</h1>';
    exit;
}

// Fetch list metadata
$list_stmt = $pdo->prepare("SELECT id, name, visibility FROM lists WHERE id = ?");
$list_stmt->execute([$list_id]);
$list = $list_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch columns (global first, then local) — sorted per EAV pattern
// list_id IS NULL = global (sorts FIRST with DESC because true > false in boolean ORDER BY)
$col_stmt = $pdo->prepare(
    "SELECT id, name, data_type, list_id
     FROM columns
     WHERE (list_id = ? OR (list_id IS NULL AND team_id = ?))
       AND is_active = TRUE
     ORDER BY (list_id IS NULL) DESC, sort_order, created_at"
);
$col_stmt->execute([$list_id, $_SESSION['team_id']]);
$columns = $col_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active players in this team
$player_stmt = $pdo->prepare(
    "SELECT id, first_name, last_name
     FROM users
     WHERE team_id = ? AND role = 'player' AND is_active = TRUE
     ORDER BY last_name, first_name"
);
$player_stmt->execute([$_SESSION['team_id']]);
$players = $player_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all cell values for this list and build map [player_id][column_id] => value
$cell_stmt = $pdo->prepare(
    "SELECT player_id, column_id, value FROM cells WHERE list_id = ?"
);
$cell_stmt->execute([$list_id]);
$cells = [];
foreach ($cell_stmt->fetchAll(PDO::FETCH_ASSOC) as $cell) {
    $cells[(int)$cell['player_id']][(int)$cell['column_id']] = $cell['value'];
}

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? 'Zeile gespeichert.' : '';

require ROOT_PATH . '/src/templates/coach/layout.php';

render_coach_page(e($list['name']), 'lists', function() use ($list, $columns, $players, $cells, $error, $success) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/coach/list_detail.php';
});
