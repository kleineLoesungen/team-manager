<?php
// src/admin/club_create_handler.php — GET: show form; POST: create club

declare(strict_types=1);

require_admin();

$error = '';
$name  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        $error = 'Der Klubname ist erforderlich.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Der Klubname darf maximal 100 Zeichen lang sein.';
    } else {
        try {
            $pdo = get_db();
            $stmt = $pdo->prepare("INSERT INTO clubs (name) VALUES (?)");
            $stmt->execute([$name]);
            redirect('/admin/clubs');
        } catch (PDOException $e) {
            error_log('Club create error: ' . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.';
        }
    }
}

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Klub hinzufügen', 'clubs', function() use ($error, $name) {
    require ROOT_PATH . '/src/templates/admin/club_form.php';
});
