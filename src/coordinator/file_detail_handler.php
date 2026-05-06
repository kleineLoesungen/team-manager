<?php
// src/coordinator/file_detail_handler.php — GET+POST /coordinator/files/{id}

declare(strict_types=1);

require_coordinator();

$file_id = (int)($_REQUEST['file_id'] ?? 0);
$pdo     = get_db();

$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND team_id = ?");
$stmt->execute([$file_id, $_SESSION['team_id']]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    redirect('/coordinator/lists');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['_action'] ?? 'save_content';

    if ($action === 'save_settings') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 255) {
            $error = 'Name ist erforderlich (max. 255 Zeichen).';
        } else {
            $visibility = in_array($_POST['visibility'] ?? '', ['public', 'protected', 'private'], true)
                ? $_POST['visibility'] : 'public';
            $is_hidden  = isset($_POST['is_hidden']) ? 'true' : 'false';
            $raw_date   = trim($_POST['date'] ?? '');

            $upd = $pdo->prepare(
                "UPDATE files
                 SET name = ?, visibility = ?, is_hidden = ?::boolean,
                     date = NULLIF(?, '')::date, updated_at = NOW()
                 WHERE id = ? AND team_id = ?"
            );
            $upd->execute([
                $name, $visibility, $is_hidden, $raw_date,
                $file_id, $_SESSION['team_id'],
            ]);
            redirect('/coordinator/files/' . $file_id . '?success=1');
        }
    } else {
        $content = $_POST['content'] ?? '';
        $upd = $pdo->prepare(
            "UPDATE files SET content = ?, updated_at = NOW() WHERE id = ? AND team_id = ?"
        );
        $upd->execute([$content, $file_id, $_SESSION['team_id']]);
        redirect('/coordinator/files/' . $file_id . '?success=1');
    }

    // Re-fetch after settings error
    $stmt->execute([$file_id, $_SESSION['team_id']]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!empty($_GET['success'])) {
    $success = 'Gespeichert.';
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page(e($file['name']), 'lists', function() use ($file, $error, $success) {
    if ($error)   echo '<div class="alert alert-danger">'  . e($error)   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/file_detail.php';
});
