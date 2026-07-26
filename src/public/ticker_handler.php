<?php
// src/public/ticker_handler.php — GET /ticker (TICKER-04, TICKER-06)
// No require_coordinator() or require_member() — intentionally public endpoint

declare(strict_types=1);

$pdo = get_db();

// Admin bypass lets us query all teams + tickers without a team context
set_admin_context($pdo);

// Fetch all active teams
$teams_rows = $pdo->query(
    "SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($teams_rows)) {
    http_response_code(404);
    exit;
}

// For each team fetch their tickers (active first, then closed)
$teams_with_tickers = [];
foreach ($teams_rows as $team) {
    set_team_context($pdo, (int)$team['id']);
    $stmt = $pdo->prepare(
        "SELECT id, name, description, status, event_date, start_time, created_at,
                (SELECT COUNT(*) FROM ticker_messages WHERE ticker_id = tickers.id) AS message_count
         FROM tickers
         WHERE team_id = ?
         ORDER BY (status = 'active') DESC, COALESCE(event_date, created_at::date) DESC, COALESCE(start_time, '00:00') DESC, created_at DESC"
    );
    $stmt->execute([(int)$team['id']]);
    $teams_with_tickers[] = [
        'team' => $team,
        'tickers' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

// Filter out teams with no tickers
$teams_with_tickers = array_values(array_filter(
    $teams_with_tickers,
    fn($t) => !empty($t['tickers'])
));

set_admin_context($pdo);
$app_title = 'Team Manager';
$stmt = $pdo->query("SELECT value FROM settings WHERE key = 'app_title' LIMIT 1");
$val = $stmt->fetchColumn();
if ($val) $app_title = $val;

// Render public template — no layout wrapper (standalone page with Bootstrap CDN)
require ROOT_PATH . '/src/templates/public/ticker_overview.php';
