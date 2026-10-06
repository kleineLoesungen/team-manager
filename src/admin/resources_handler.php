<?php
// src/admin/resources_handler.php — GET: list resources; POST: add one
// Ressourcen (Platz, Halle, Bus …) gelten für alle Teams; Koordinatoren belegen sie bei Listen
// und Terminen (src/db/resources.php).

declare(strict_types=1);

require_admin();
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) {
        redirect('/admin/resources?error=' . urlencode('Gib einen Namen mit höchstens 100 Zeichen ein.'));
    }
    $pdo->prepare("INSERT INTO resources (name) VALUES (?)")->execute([$name]);
    redirect('/admin/resources?success=' . urlencode($name . ' hinzugefügt.'));
}

$resources = $pdo->query(
    "SELECT r.id, r.name, r.is_active,
            (SELECT COUNT(*) FROM resource_bookings b WHERE b.resource_id = r.id) AS bookings
     FROM resources r ORDER BY r.is_active DESC, r.name"
)->fetchAll(PDO::FETCH_ASSOC);

$active   = array_values(array_filter($resources, fn($r) => in_array($r['is_active'], [true, 1, '1', 't'], true)));
$inactive = array_values(array_filter($resources, fn($r) => !in_array($r['is_active'], [true, 1, '1', 't'], true)));
$error    = (string)($_GET['error'] ?? '');
$success  = (string)($_GET['success'] ?? '');

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Ressourcen', 'settings', function() use ($active, $inactive, $error, $success) {
    require ROOT_PATH . '/src/templates/admin/resources.php';
});
