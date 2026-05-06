<?php
// src/coordinator/lists_handler.php — GET /coordinator/lists — overview for coordinator

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT id, name, visibility, is_hidden, date, created_at,
            'list' AS type
     FROM lists
     WHERE team_id = ?"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$files = [];
if (defined('DB_HAS_FILES') && DB_HAS_FILES) {
    $fstmt = $pdo->prepare(
        "SELECT id, name, visibility, is_hidden, date, created_at,
                'file' AS type
         FROM files
         WHERE team_id = ?"
    );
    $fstmt->execute([$_SESSION['team_id']]);
    $files = $fstmt->fetchAll(PDO::FETCH_ASSOC);
}

$items = array_merge($lists, $files);
usort($items, function(array $a, array $b): int {
    $ad = $a['date'];
    $bd = $b['date'];
    if ($ad !== $bd) {
        if ($ad === null) return 1;
        if ($bd === null) return -1;
        $cmp = strcmp($bd, $ad);
        if ($cmp !== 0) return $cmp;
    }
    return strcmp($b['created_at'], $a['created_at']);
});

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? 'Gespeichert.' : '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Inhalte', 'lists', function() use ($items, $error, $success) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/lists.php';
});
