<?php
// src/coordinator/file_create_handler.php — GET+POST /coordinator/files/create

declare(strict_types=1);

require_coordinator();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    if ($name === '' || mb_strlen($name) > 255) {
        $error = 'Name ist erforderlich (max. 255 Zeichen).';
    } else {
        $pdo        = get_db();
        $visibility = in_array($_POST['visibility'] ?? '', ['public', 'protected', 'private'], true)
            ? $_POST['visibility']
            : 'public';
        $is_hidden  = isset($_POST['is_hidden']) ? 'true' : 'false';
        $raw_date   = trim($_POST['date'] ?? '');

        $stmt = $pdo->prepare(
            "INSERT INTO files (team_id, name, visibility, is_hidden, date)
             VALUES (?, ?, ?, ?::boolean, NULLIF(?, '')::date)
             RETURNING id"
        );
        $stmt->execute([
            $_SESSION['team_id'],
            $name,
            $visibility,
            $is_hidden,
            $raw_date,
        ]);
        $file_id = (int)$stmt->fetchColumn();
        redirect('/coordinator/files/' . $file_id);
    }
}

$pdo = $pdo ?? get_db();
require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Neue Datei', 'lists', function() use ($error) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/file_form.php';
});
