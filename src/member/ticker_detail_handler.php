<?php
// src/member/ticker_detail_handler.php — GET/POST /member/ticker/{id} (TICKER-02, TICKER-03)

declare(strict_types=1);

require_member();

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);
$pdo = get_db();

// Verify this ticker belongs to the member's team
$stmt = $pdo->prepare(
    "SELECT id, name, description, status FROM tickers WHERE id = ? AND team_id = ?"
);
$stmt->execute([$ticker_id, $_SESSION['team_id']]);
$ticker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticker) {
    http_response_code(404);
    echo '<h1>Ticker nicht gefunden</h1>';
    exit;
}

// Check freigabe: member must be in ticker_members for this ticker (TICKER-03)
$stmt = $pdo->prepare(
    "SELECT 1 FROM ticker_members WHERE ticker_id = ? AND user_id = ? AND team_id = ?"
);
$stmt->execute([$ticker_id, $_SESSION['user_id'], $_SESSION['team_id']]);
$is_freigegeben = (bool)$stmt->fetchColumn();

// Non-freigegeben members can view but NOT post
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_freigegeben) {
        http_response_code(403);
        echo '<h1>403 — Du darfst in diesem Ticker nicht posten.</h1>';
        exit;
    }

    require_csrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'post_message') {
        $message   = trim($_POST['message'] ?? '');
        $timestamp = trim($_POST['timestamp'] ?? date('H:i'));
        $tag_id    = !empty($_POST['tag_id']) ? (int)$_POST['tag_id'] : null;

        if (mb_strlen($message, 'UTF-8') === 0) {
            $error = 'Nachricht darf nicht leer sein.';
        } elseif (mb_strlen($message, 'UTF-8') > 280) {
            $error = 'Nachricht darf maximal 280 Zeichen lang sein.';
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $timestamp)) {
            $error = 'Uhrzeit muss Format HH:MM haben.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO ticker_messages (ticker_id, tag_id, message, timestamp, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([$ticker_id, $tag_id, $message, $timestamp]);
            redirect("/member/ticker/$ticker_id");
        }
    }

    if ($action === 'delete_message') {
        $msg_id = (int)($_POST['message_id'] ?? 0);
        if ($msg_id > 0) {
            // D-12: freigegeben members can delete ANY message (not just their own)
            $stmt = $pdo->prepare(
                "DELETE FROM ticker_messages WHERE id = ? AND ticker_id = ?"
            );
            $stmt->execute([$msg_id, $ticker_id]);
        }
        redirect("/member/ticker/$ticker_id");
    }

    if ($action === 'edit_message') {
        $msg_id    = (int)($_POST['message_id'] ?? 0);
        $message   = trim($_POST['message'] ?? '');
        $timestamp = trim($_POST['timestamp'] ?? date('H:i'));
        $tag_id    = !empty($_POST['tag_id']) ? (int)$_POST['tag_id'] : null;

        if (mb_strlen($message, 'UTF-8') === 0) {
            $error = 'Nachricht darf nicht leer sein.';
        } elseif (mb_strlen($message, 'UTF-8') > 280) {
            $error = 'Nachricht darf maximal 280 Zeichen lang sein.';
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $timestamp)) {
            $error = 'Uhrzeit muss Format HH:MM haben.';
        } elseif ($msg_id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE ticker_messages
                 SET message = ?, timestamp = ?, tag_id = ?, updated_at = NOW()
                 WHERE id = ? AND ticker_id = ?"
            );
            $stmt->execute([$message, $timestamp, $tag_id, $msg_id, $ticker_id]);
            redirect("/member/ticker/$ticker_id");
        }
    }
}

// Fetch data for GET (and POST with validation error)
$edit_msg_id = (int)($_GET['edit_message_id'] ?? 0);
$edit_message = null;
if ($edit_msg_id > 0 && $is_freigegeben) {
    $stmt = $pdo->prepare("SELECT * FROM ticker_messages WHERE id = ? AND ticker_id = ?");
    $stmt->execute([$edit_msg_id, $ticker_id]);
    $edit_message = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$stmt = $pdo->prepare(
    "SELECT m.id, m.message, m.timestamp, m.tag_id, m.created_at,
            t.label AS tag_label, t.color AS tag_color
     FROM ticker_messages m
     LEFT JOIN ticker_tags t ON m.tag_id = t.id
     WHERE m.ticker_id = ?
     ORDER BY m.created_at DESC"
);
$stmt->execute([$ticker_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT id, label, color FROM ticker_tags WHERE team_id = ? ORDER BY sort_order, created_at"
);
$stmt->execute([$_SESSION['team_id']]);
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

require ROOT_PATH . '/src/templates/member/layout.php';

render_player_page(e($ticker['name']), 'ticker', function() use ($ticker, $messages, $tags, $is_freigegeben, $error, $edit_message, $ticker_id) {
    if ($error) echo '<div class="alert alert-danger">' . e($error) . '</div>';
    require ROOT_PATH . '/src/templates/member/ticker_detail.php';
});
