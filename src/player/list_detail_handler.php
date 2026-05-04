<?php
// src/player/list_detail_handler.php — GET /player/lists/{id} — list table for player
// public: player edits own row; protected: player sees own row read-only; private: denied.
// show_all_rows flag controls whether all rows or only own row is displayed.

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/visibility.php';
require_player();

$list_id         = (int)($_REQUEST['list_id'] ?? 0);
$pdo             = get_db();
$current_user_id = (int)$_SESSION['user_id'];

if (!can_view_list($list_id)) {
    http_response_code(404);
    echo '<h1>Liste nicht gefunden</h1>';
    exit;
}

// Fetch list metadata including show_all_rows
$list_stmt = $pdo->prepare("SELECT id, name, visibility, show_all_rows, date, description FROM lists WHERE id = ?");
$list_stmt->execute([$list_id]);
$list = $list_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch columns: local + global columns selected for this list (D-11)
// coach_only filter only added when column exists in DB (migration may not have run yet)
$local_filter = DB_HAS_COACH_ONLY ? '(c.list_id = ? AND c.coach_only = FALSE)' : 'c.list_id = ?';
$col_stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.data_type, c.list_id
     FROM columns c
     WHERE c.is_active = TRUE
       AND (
         {$local_filter}
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

// Row visibility: show all rows or only own row
if ($list['show_all_rows']) {
    $player_stmt = $pdo->prepare(
        "SELECT id, first_name, last_name
         FROM users
         WHERE team_id = ? AND role = 'player' AND is_active = TRUE
         ORDER BY first_name, last_name"
    );
    $player_stmt->execute([$_SESSION['team_id']]);
} else {
    $player_stmt = $pdo->prepare(
        "SELECT id, first_name, last_name
         FROM users
         WHERE id = ? AND team_id = ? AND role = 'player' AND is_active = TRUE"
    );
    $player_stmt->execute([$current_user_id, $_SESSION['team_id']]);
}
$players = $player_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch cells — only for visible player rows
if ($list['show_all_rows']) {
    $cell_stmt = $pdo->prepare("SELECT player_id, column_id, value FROM cells WHERE list_id = ?");
    $cell_stmt->execute([$list_id]);
} else {
    $cell_stmt = $pdo->prepare("SELECT player_id, column_id, value FROM cells WHERE list_id = ? AND player_id = ?");
    $cell_stmt->execute([$list_id, $current_user_id]);
}
$cells = [];
foreach ($cell_stmt->fetchAll(PDO::FETCH_ASSOC) as $cell) {
    $cells[(int)$cell['player_id']][(int)$cell['column_id']] = $cell['value'];
}

$success = !empty($_GET['success']) ? 'Gespeichert.' : '';

require ROOT_PATH . '/src/templates/player/layout.php';

render_player_page(e($list['name']), 'lists', function() use ($list, $columns, $players, $cells, $current_user_id, $success) {
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/player/list_detail.php';
});
