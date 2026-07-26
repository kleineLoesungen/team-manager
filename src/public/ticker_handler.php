<?php
// src/public/ticker_handler.php — GET /ticker (TICKER-04, TICKER-06)
// No require_coordinator() or require_member() — intentionally public endpoint

declare(strict_types=1);

// Default to team 1; override via ?team=N for multi-team setups
$team_id = (int)($_GET['team'] ?? 1);

if ($team_id <= 0) {
    http_response_code(404);
    exit;
}

$pdo = get_db();

// Set RLS context for team isolation (no role — public access)
set_team_context($pdo, $team_id);

// Verify team exists and is active
$stmt = $pdo->prepare("SELECT id, name FROM teams WHERE id = ? AND is_active = TRUE");
$stmt->execute([$team_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    http_response_code(404);
    exit;
}

// Fetch tickers: active first, then closed (TICKER-06)
$stmt = $pdo->prepare(
    "SELECT id, name, description, status, created_at,
            (SELECT COUNT(*) FROM ticker_messages WHERE ticker_id = tickers.id) AS message_count
     FROM tickers
     WHERE team_id = ?
     ORDER BY (status = 'active') DESC, created_at DESC"
);
$stmt->execute([$team_id]);
$tickers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$app_title = 'Team Manager';
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_title'");
$stmt->execute();
$val = $stmt->fetchColumn();
if ($val) $app_title = $val;

// Render public template — no layout wrapper (standalone page with Bootstrap CDN)
require ROOT_PATH . '/src/templates/public/ticker_overview.php';
