<?php
// src/member/ticker_handler.php — GET /member/ticker (TICKER-02, TICKER-03)

declare(strict_types=1);

require_member();

$pdo = get_db();

// Show all team tickers
$stmt = $pdo->prepare(
    "SELECT t.id, t.name, t.description, t.status, t.created_at
     FROM tickers t
     WHERE t.team_id = ?
     ORDER BY (t.status = 'active') DESC, t.created_at DESC"
);
$stmt->execute([$_SESSION['team_id']]);
$tickers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tickers from other teams this member also belongs to (same profile)
set_admin_context($pdo);
$other_stmt = $pdo->prepare(
    "SELECT DISTINCT t.id, t.name, t.description, t.status, t.created_at,
            tm.name AS team_name,
            (t.status = 'active') AS is_active_ticker
     FROM users u
     JOIN teams tm ON tm.id = u.team_id
     JOIN tickers t ON t.team_id = u.team_id
     WHERE u.member_id = (SELECT member_id FROM users WHERE id = ? LIMIT 1)
       AND u.team_id != ?
       AND u.role = 'member'
       AND u.is_active = TRUE
       AND tm.is_active = TRUE
     ORDER BY (t.status = 'active') DESC, t.created_at DESC"
);
$other_stmt->execute([$_SESSION['user_id'], $_SESSION['team_id']]);
$other_tickers = $other_stmt->fetchAll(PDO::FETCH_ASSOC);
reset_rls_context($pdo);
set_team_context($pdo, (int)$_SESSION['team_id'], 'member', (int)$_SESSION['user_id']);

require ROOT_PATH . '/src/templates/member/layout.php';

render_member_page('Ticker', 'ticker', function() use ($tickers, $other_tickers) {
    require ROOT_PATH . '/src/templates/member/ticker_list.php';
});
