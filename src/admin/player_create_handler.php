<?php
// src/admin/player_create_handler.php — GET: show form; POST: create player with optional team assignment

declare(strict_types=1);

require_admin();
$pdo = get_db();

require ROOT_PATH . '/src/templates/admin/layout.php';

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name")->fetchAll();
$error = '';
$form  = ['first_name' => '', 'last_name' => '', 'club_id' => 0, 'email' => '',
           'phone' => '', 'contact_name' => '', 'contact_phone' => '', 'contact_email' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form['first_name']    = trim($_POST['first_name']    ?? '');
    $form['last_name']     = trim($_POST['last_name']     ?? '');
    $form['club_id']       = (int)($_POST['club_id']      ?? 0);
    $form['email']         = trim($_POST['email']         ?? '');
    $form['phone']         = trim($_POST['phone']         ?? '');
    $form['contact_name']  = trim($_POST['contact_name']  ?? '');
    $form['contact_phone'] = trim($_POST['contact_phone'] ?? '');
    $form['contact_email'] = trim($_POST['contact_email'] ?? '');
    $form['description']   = trim($_POST['description']   ?? '');

    if (empty($form['first_name']) || empty($form['last_name'])) {
        $error = 'Vor- und Nachname sind erforderlich.';
    } elseif ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse.';
    } elseif ($form['contact_email'] !== '' && !filter_var($form['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige Kontakt-E-Mail-Adresse.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO members (club_id, first_name, last_name, email, phone, contact_name, contact_phone, contact_email, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
            );
            $stmt->execute([
                $form['club_id'] > 0 ? $form['club_id'] : null,
                $form['first_name'], $form['last_name'],
                $form['email'] !== '' ? $form['email'] : null,
                $form['phone'] !== '' ? $form['phone'] : null,
                $form['contact_name'] !== '' ? $form['contact_name'] : null,
                $form['contact_phone'] !== '' ? $form['contact_phone'] : null,
                $form['contact_email'] !== '' ? $form['contact_email'] : null,
                $form['description'] !== '' ? $form['description'] : null,
            ]);
            $player_id = (int)$stmt->fetchColumn();
            redirect('/admin/players');
        } catch (PDOException $e) {
            error_log('Player create error: ' . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.';
        }
    }
}

render_admin_page('Mitglied hinzufügen', 'players', function() use ($clubs, $error, $form) {
    require ROOT_PATH . '/src/templates/admin/player_form.php';
});
