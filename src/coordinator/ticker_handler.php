<?php
// src/coordinator/ticker_handler.php — GET /coordinator/ticker

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT id, name, description, status, created_at
     FROM tickers
     WHERE team_id = ?
     ORDER BY (status = 'active') DESC, created_at DESC"
);
$stmt->execute([$_SESSION['team_id']]);
$tickers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = !empty($_GET['success']) ? match($_GET['success']) {
    'created'   => 'Ticker angelegt.',
    'closed'    => 'Ticker geschlossen.',
    'deleted'   => 'Ticker gelöscht.',
    default     => '',
} : '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Ticker', 'ticker', function() use ($tickers, $success) {
    if ($success) echo '<div class="alert alert-success">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/ticker.php';
});
