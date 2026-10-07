<?php
// src/admin/organization_create_handler.php — GET: show form; POST: create organization

declare(strict_types=1);

require_admin();

$error = '';
$name  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        $error = 'Gib den Namen der Organisation ein.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Der Name darf höchstens 100 Zeichen lang sein.';
    } else {
        try {
            $pdo = get_db();
            $stmt = $pdo->prepare("INSERT INTO organizations (name) VALUES (?)");
            $stmt->execute([$name]);
            redirect('/admin/organizations');
        } catch (PDOException $e) {
            error_log('Organization create error: ' . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.';
        }
    }
}

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Organisation hinzufügen', 'organizations', function() use ($error, $name) {
    require ROOT_PATH . '/src/templates/admin/organization_form.php';
});
