<?php
// src/coordinator/ticker_create_handler.php — GET/POST /coordinator/ticker/new

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

// Fetch team members for freigabe checkboxes (D-11)
$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name
     FROM users
     WHERE team_id = ? AND role = 'member' AND is_active = TRUE
     ORDER BY first_name, last_name"
);
$stmt->execute([$_SESSION['team_id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$form_values = ['name' => '', 'description' => '', 'event_date' => date('Y-m-d'), 'start_time' => '', 'freigabe_members' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date  = trim($_POST['event_date'] ?? '');
    $start_time  = trim($_POST['start_time'] ?? '');
    $freigabe    = isset($_POST['freigabe_members']) && is_array($_POST['freigabe_members'])
                   ? array_map('intval', $_POST['freigabe_members'])
                   : [];

    $event_date_val = null;
    if ($event_date !== '') {
        $d = \DateTime::createFromFormat('Y-m-d', $event_date);
        if ($d && $d->format('Y-m-d') === $event_date) {
            $event_date_val = $event_date;
        }
    }

    $start_time_val = null;
    if ($start_time !== '' && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) {
        $start_time_val = substr($start_time, 0, 5) . ':00';
    }

    if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
        $error = 'Ticker-Name ist erforderlich (max. 255 Zeichen).';
        $form_values = compact('name', 'description', 'event_date', 'start_time', 'freigabe');
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO tickers (team_id, name, description, status, event_date, start_time, created_at, updated_at)
                 VALUES (?, ?, ?, 'active', ?, ?, NOW(), NOW())
                 RETURNING id"
            );
            $stmt->execute([$_SESSION['team_id'], $name, $description ?: null, $event_date_val, $start_time_val]);
            $ticker_id = (int)$stmt->fetchColumn();

            // Insert freigabe members (D-11)
            if (!empty($freigabe)) {
                $insert = $pdo->prepare(
                    "INSERT INTO ticker_members (ticker_id, user_id, team_id)
                     VALUES (?, ?, ?)
                     ON CONFLICT DO NOTHING"
                );
                foreach ($freigabe as $member_id) {
                    $insert->execute([$ticker_id, $member_id, $_SESSION['team_id']]);
                }
            }

            $pdo->commit();
            redirect('/coordinator/ticker/' . $ticker_id);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $error = 'Fehler beim Anlegen des Tickers. Bitte versuche es erneut.';
            error_log('ticker_create_handler: ' . $e->getMessage());
        }
    }
}

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Neuer Ticker', 'ticker', function() use ($members, $error, $form_values) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/ticker_form.php';
});
