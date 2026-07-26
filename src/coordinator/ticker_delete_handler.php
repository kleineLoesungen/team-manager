<?php
// src/coordinator/ticker_delete_handler.php — GET/POST /coordinator/ticker/{id}/delete

declare(strict_types=1);

require_coordinator();

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT id, name FROM tickers WHERE id = ? AND team_id = ?"
);
$stmt->execute([$ticker_id, $_SESSION['team_id']]);
$ticker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticker) {
    http_response_code(404);
    echo '<h1>Ticker nicht gefunden</h1>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $stmt = $pdo->prepare("DELETE FROM tickers WHERE id = ? AND team_id = ?");
    $stmt->execute([$ticker_id, $_SESSION['team_id']]);

    redirect('/coordinator/ticker?success=deleted');
}

// GET: show confirmation page
require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Ticker löschen', 'ticker', function() use ($ticker) {
    require ROOT_PATH . '/src/templates/coordinator/ticker_delete_confirm.php';
});
