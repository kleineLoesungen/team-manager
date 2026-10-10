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

$stmt = $pdo->prepare("SELECT id, team_id, name, description, status, event_date, start_time FROM tickers WHERE id = ?");
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

// Angemeldet und im Team (Koordinatoren auch in weiteren betreuten Teams): eigene Ansicht
// mit Zuschauerzahl, Abo und Posten. Geteilte Links zeigen auf diese öffentliche Seite.
$role = $_SESSION['role'] ?? '';
if (!empty($_SESSION['user_id']) && empty($_SESSION['pending_team_pick'])
    && in_array($role, ['coordinator', 'member'], true)
    && (time() - (int)($_SESSION['last_activity'] ?? 0)) <= SESSION_TIMEOUT) {
    $own_team = (int)$ticker['team_id'] === (int)($_SESSION['team_id'] ?? 0);
    if (!$own_team && $role === 'coordinator') {
        set_admin_context($pdo);
        $ct = $pdo->prepare("SELECT 1 FROM coordinator_teams WHERE user_id = ? AND team_id = ? AND left_at IS NULL");
        $ct->execute([(int)$_SESSION['user_id'], (int)$ticker['team_id']]);
        $own_team = (bool)$ct->fetchColumn();
        reset_rls_context($pdo);
        set_team_context($pdo, (int)$ticker['team_id']);
    }
    if ($own_team) {
        redirect('/' . $role . '/ticker/' . $ticker_id);
    }
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

// Ticker abonnieren als Gast: Abo hängt an diesem Gerät (Cookie tm_device, Issue #15)
$push_subscribed = push_ticker_is_subscribed($pdo, $ticker_id);
$push_key        = push_vapid($pdo)['public'];

// Render public template (TICKER-05: auto-reload logic in template)
require ROOT_PATH . '/src/templates/public/ticker_detail.php';
