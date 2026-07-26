<?php
// src/member/ticker_handler.php — GET /member/ticker (TICKER-02, TICKER-03)

declare(strict_types=1);

require_member();

$pdo = get_db();

// Only show tickers where this member is freigegeben (D-13)
$stmt = $pdo->prepare(
    "SELECT t.id, t.name, t.description, t.status, t.created_at
     FROM tickers t
     INNER JOIN ticker_members tm ON tm.ticker_id = t.id
     WHERE tm.user_id = ? AND t.team_id = ?
     ORDER BY (t.status = 'active') DESC, t.created_at DESC"
);
$stmt->execute([$_SESSION['user_id'], $_SESSION['team_id']]);
$tickers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require ROOT_PATH . '/src/templates/member/layout.php';

render_player_page('Ticker', 'ticker', function() use ($tickers) {
    require ROOT_PATH . '/src/templates/member/ticker_list.php';
});
