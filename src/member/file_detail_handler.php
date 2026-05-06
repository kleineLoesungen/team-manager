<?php
// src/member/file_detail_handler.php — GET+POST /member/files/{id}
// Public files: member can view and edit content. Protected: read-only.

declare(strict_types=1);

require_member();

$file_id = (int)($_REQUEST['file_id'] ?? 0);
$pdo     = get_db();

// RLS already filters to this team + visibility; also exclude private files explicitly
$stmt = $pdo->prepare(
    "SELECT * FROM files WHERE id = ? AND visibility IN ('public', 'protected')"
);
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    redirect('/member/lists');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($file['visibility'] !== 'public') {
        redirect('/member/files/' . $file_id);
    }

    $content = $_POST['content'] ?? '';
    $upd = $pdo->prepare(
        "UPDATE files SET content = ?, updated_at = NOW()
         WHERE id = ? AND visibility = 'public'"
    );
    $upd->execute([$content, $file_id]);
    redirect('/member/files/' . $file_id . '?success=1');
}

if (!empty($_GET['success'])) {
    $success = 'Gespeichert.';
}

require ROOT_PATH . '/src/templates/member/layout.php';

render_player_page(e($file['name']), 'lists', function() use ($file, $error, $success) {
    if ($error)   echo '<div class="alert alert-danger">'  . e($error)   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/member/file_detail.php';
});
