<?php
// src/coordinator/ticker_edit_handler.php — GET/POST /coordinator/ticker/{id}/edit

declare(strict_types=1);

require_coordinator();

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT id, name, description, status, event_date, start_time FROM tickers
     WHERE id = ? AND team_id = ?"
);
$stmt->execute([$ticker_id, $_SESSION['team_id']]);
$ticker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticker) {
    http_response_code(404);
    echo '<h1>Ticker nicht gefunden</h1>';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name FROM users
     WHERE team_id = ? AND role = 'member' AND is_active = TRUE
     ORDER BY first_name, last_name"
);
$stmt->execute([$_SESSION['team_id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT user_id FROM ticker_members WHERE ticker_id = ? AND team_id = ?"
);
$stmt->execute([$ticker_id, $_SESSION['team_id']]);
$current_freigabe = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id');

$error = '';
$form_values = [
    'name'            => $ticker['name'],
    'description'     => $ticker['description'] ?? '',
    'event_date'      => $ticker['event_date'] ?? '',
    'start_time'      => $ticker['start_time'] ? substr($ticker['start_time'], 0, 5) : '',
    'freigabe_members' => array_map('intval', $current_freigabe),
];

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
        $form_values['freigabe_members'] = $freigabe;
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE tickers SET name = ?, description = ?, event_date = ?, start_time = ?, updated_at = NOW()
                 WHERE id = ? AND team_id = ?"
            )->execute([$name, $description ?: null, $event_date_val, $start_time_val, $ticker_id, $_SESSION['team_id']]);

            // Replace freigabe members
            $pdo->prepare("DELETE FROM ticker_members WHERE ticker_id = ? AND team_id = ?")
                ->execute([$ticker_id, $_SESSION['team_id']]);

            if (!empty($freigabe)) {
                $insert = $pdo->prepare(
                    "INSERT INTO ticker_members (ticker_id, user_id, team_id) VALUES (?, ?, ?) ON CONFLICT DO NOTHING"
                );
                foreach ($freigabe as $member_id) {
                    $insert->execute([$ticker_id, $member_id, $_SESSION['team_id']]);
                }
            }

            $pdo->commit();
            redirect('/coordinator/ticker/' . $ticker_id);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $error = 'Fehler beim Speichern. Bitte versuche es erneut.';
            error_log('ticker_edit_handler: ' . $e->getMessage());
        }
    }
}

$form_action = '/coordinator/ticker/' . $ticker_id . '/edit';
$cancel_url  = '/coordinator/ticker/' . $ticker_id;

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Ticker bearbeiten', 'ticker', function() use ($members, $error, $form_values, $form_action, $cancel_url) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/ticker_form.php';
});
