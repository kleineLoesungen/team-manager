<?php
// src/coach/list_create_handler.php — GET/POST /coach/lists/create (LIST-01, LIST-04)

declare(strict_types=1);

require_coach();

$pdo   = get_db();
$error = '';

// Fetch all active global columns for this team (for checkbox display in form — D-11)
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
    $selected_cols = array_map('intval', (array)($_POST['global_columns'] ?? []));

    if (empty($name)) {
        $error = 'Name ist erforderlich.';
    } elseif (!in_array($visibility, ['public', 'protected', 'private'])) {
        $error = 'Ungültiger Sichtbarkeits-Status.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO lists (team_id, name, visibility)
                 VALUES (?, ?, ?)
                 RETURNING id"
            );
            $stmt->execute([$_SESSION['team_id'], $name, $visibility]);
            $list_id = (int)$stmt->fetchColumn();

            // NOTE: global columns are NOT stored per-list — they are available in all lists of
            // the team automatically (queried by team_id, list_id IS NULL). The checkboxes in
            // the create form are informational UX only; no DB association is needed.

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
