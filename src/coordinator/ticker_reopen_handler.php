<?php
// src/coordinator/ticker_reopen_handler.php — POST /coordinator/ticker/{id}/reopen

declare(strict_types=1);

require_coordinator();
require_csrf();

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare(
    "UPDATE tickers SET status = 'active', updated_at = NOW()
     WHERE id = ? AND team_id = ?"
);
$stmt->execute([$ticker_id, $_SESSION['team_id']]);

redirect('/coordinator/ticker/' . $ticker_id);
