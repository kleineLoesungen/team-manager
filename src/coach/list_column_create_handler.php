<?php
// src/coach/list_column_create_handler.php — POST /moderator/lists/{id}/columns/create (LIST-03)
// Creates a LOCAL column (list_id IS NOT NULL). Text type IS allowed for local columns.

declare(strict_types=1);

require_coach();

$list_id = (int)($_REQUEST['list_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/moderator/lists/' . $list_id);
}

require_csrf();

$pdo       = get_db();
$name      = trim($_POST['name'] ?? '');
$data_type = $_POST['data_type'] ?? '';
$coach_only = isset($_POST['coach_only']) && $_POST['coach_only'] === '1';

// Verify list belongs to this coach's team
$check = $pdo->prepare("SELECT id FROM lists WHERE id = ? AND team_id = ?");
$check->execute([$list_id, $_SESSION['team_id']]);
if (!$check->fetch()) {
    http_response_code(404);
    echo '<h1>Liste nicht gefunden</h1>';
    exit;
}

if (empty($name)) {
    redirect('/moderator/lists/' . $list_id . '?error=' . urlencode('Spaltenname ist erforderlich.'));
}
// Local columns allow boolean, number, text (unlike global columns)
if (!in_array($data_type, ['boolean', 'number', 'text'])) {
    redirect('/moderator/lists/' . $list_id . '?error=' . urlencode('Ungültiger Spaltentyp.'));
}

try {
    if (DB_HAS_COACH_ONLY) {
        $stmt = $pdo->prepare(
            "INSERT INTO columns (team_id, list_id, name, data_type, coach_only)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$_SESSION['team_id'], $list_id, $name, $data_type, $coach_only ? 1 : 0]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO columns (team_id, list_id, name, data_type)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$_SESSION['team_id'], $list_id, $name, $data_type]);
    }
    redirect('/moderator/lists/' . $list_id . '?success=1');
} catch (PDOException $e) {
    error_log('Local column create error: ' . $e->getMessage());
    redirect('/moderator/lists/' . $list_id . '?error=' . urlencode('Ein Fehler ist aufgetreten.'));
}
