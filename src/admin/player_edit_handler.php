<?php
// src/admin/player_edit_handler.php — GET: show edit form; POST: update player
// $_REQUEST['player_id'] set by router

declare(strict_types=1);

require_admin();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
if ($player_id <= 0) {
    redirect('/admin/players');
}

$pdo  = get_db();
$stmt = $pdo->prepare(
    "SELECT p.id, p.first_name, p.last_name, p.club_id, p.email, p.phone,
            p.contact_name, p.contact_phone, p.contact_email, p.description, p.is_active
     FROM players p
     WHERE p.id = ?"
);
$stmt->execute([$player_id]);
$player = $stmt->fetch();

if (!$player) {
    redirect('/admin/players');
}

$clubs = $pdo->query("SELECT id, name FROM clubs WHERE is_active = TRUE ORDER BY name ASC")->fetchAll();

// Determine whether player can be deleted (must be inactive + no linked users)
$linked_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE player_id = ?");
$linked_count_stmt->execute([$player_id]);
$can_delete = !$player['is_active'] && (int)$linked_count_stmt->fetchColumn() === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $first_name    = trim($_POST['first_name']    ?? '');
    $last_name     = trim($_POST['last_name']     ?? '');
    $club_id       = (int)($_POST['club_id']      ?? 0);
    $email_raw     = trim($_POST['email']         ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $contact_name  = trim($_POST['contact_name']  ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $description   = trim($_POST['description']   ?? '');

    if (empty($first_name) || empty($last_name)) {
        redirect('/admin/players/' . $player_id . '/edit?error=' . urlencode('Vor- und Nachname sind erforderlich.'));
    }
    if ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        redirect('/admin/players/' . $player_id . '/edit?error=' . urlencode('Ungültige E-Mail-Adresse.'));
    }
    if ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        redirect('/admin/players/' . $player_id . '/edit?error=' . urlencode('Ungültige Kontakt-E-Mail-Adresse.'));
    }

    $pdo->prepare(
        "UPDATE players SET club_id = ?, first_name = ?, last_name = ?, email = ?,
                            phone = ?, contact_name = ?, contact_phone = ?, contact_email = ?, description = ?
         WHERE id = ?"
    )->execute([
        $club_id > 0 ? $club_id : null,
        $first_name, $last_name,
        $email_raw !== '' ? $email_raw : null,
        $phone !== '' ? $phone : null,
        $contact_name !== '' ? $contact_name : null,
        $contact_phone !== '' ? $contact_phone : null,
        $contact_email !== '' ? $contact_email : null,
        $description !== '' ? $description : null,
        $player_id,
    ]);

    redirect('/admin/players?success=' . urlencode($first_name . ' ' . $last_name . ' gespeichert.'));
}

// GET: render edit form
$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Spieler bearbeiten', 'players', function() use ($player, $clubs, $error, $can_delete) {
    require ROOT_PATH . '/src/templates/admin/player_edit.php';
});
