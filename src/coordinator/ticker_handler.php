<?php
// src/coordinator/ticker_handler.php — GET /coordinator/ticker

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT id, name, description, status, event_date, start_time, created_at,
            (SELECT COUNT(*) FROM ticker_messages WHERE ticker_id = tickers.id) AS message_count
     FROM tickers
     WHERE team_id = ?
     ORDER BY (status = 'active') DESC, COALESCE(event_date, created_at::date) DESC, created_at DESC"
);
$stmt->execute([$_SESSION['team_id']]);
$tickers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tickers from other teams this coordinator also manages
set_admin_context($pdo);
$other_stmt = $pdo->prepare(
    "SELECT t.id, t.name, t.description, t.status, t.event_date, t.start_time, t.created_at,
            tm.name AS team_name,
            (SELECT COUNT(*) FROM ticker_messages WHERE ticker_id = t.id) AS message_count
     FROM coordinator_teams ct
     JOIN teams tm ON tm.id = ct.team_id
     JOIN tickers t ON t.team_id = ct.team_id
     WHERE ct.user_id = ? AND ct.team_id != ? AND ct.left_at IS NULL AND tm.is_active = TRUE
     ORDER BY (t.status = 'active') DESC, COALESCE(t.event_date, t.created_at::date) DESC, t.created_at DESC"
);
$other_stmt->execute([$_SESSION['user_id'], $_SESSION['team_id']]);
$other_tickers = $other_stmt->fetchAll(PDO::FETCH_ASSOC);
reset_rls_context($pdo);
set_team_context($pdo, (int)$_SESSION['team_id'], 'coordinator', (int)$_SESSION['user_id']);

$success = !empty($_GET['success']) ? match($_GET['success']) {
    'created'   => 'Ticker angelegt.',
    'closed'    => 'Ticker geschlossen.',
    'deleted'   => 'Ticker gelöscht.',
    default     => '',
} : '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Ticker', 'ticker', function() use ($tickers, $other_tickers, $success) {
    if ($success) echo '<div class="alert alert-success">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/ticker.php';
});
