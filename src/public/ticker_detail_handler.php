<?php
// src/public/ticker_detail_handler.php — GET /ticker/{id} (TICKER-04, TICKER-05)
// No require_coordinator() or require_member() — intentionally public endpoint

declare(strict_types=1);

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);

if ($ticker_id <= 0) {
    http_response_code(404);
    exit;
}

$pdo = get_db();

// Step 1: Use admin bypass to look up the ticker by ID (no team context yet)
// This is necessary because we don't know team_id until we read the ticker
set_admin_context($pdo);

$stmt = $pdo->prepare("SELECT id, team_id, name, description, status FROM tickers WHERE id = ?");
$stmt->execute([$ticker_id]);
$ticker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticker) {
    http_response_code(404);
    exit;
}

// Step 2: Restore proper team isolation (no role — public access)
set_team_context($pdo, (int)$ticker['team_id']);

// Verify team is active
$stmt = $pdo->prepare("SELECT id, name FROM teams WHERE id = ? AND is_active = TRUE");
$stmt->execute([$ticker['team_id']]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    http_response_code(404);
    exit;
}

// Fetch messages (newest first, D-05) with optional tag info
$stmt = $pdo->prepare(
    "SELECT m.id, m.message, m.timestamp, m.tag_id, m.created_at,
            t.label AS tag_label, t.color AS tag_color
     FROM ticker_messages m
     LEFT JOIN ticker_tags t ON m.tag_id = t.id
     WHERE m.ticker_id = ?
     ORDER BY m.timestamp::time DESC, m.created_at DESC
     LIMIT 100"
);
$stmt->execute([$ticker_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$app_title = 'Team Manager';
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_title'");
$stmt->execute();
$val = $stmt->fetchColumn();
if ($val) $app_title = $val;

// Render public template (TICKER-05: auto-reload logic in template)
require ROOT_PATH . '/src/templates/public/ticker_detail.php';
