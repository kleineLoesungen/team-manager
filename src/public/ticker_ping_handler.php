<?php
// src/public/ticker_ping_handler.php — POST /ticker/{id}/ping
// Heartbeat of an open ticker page (see src/db/ticker_viewers.php). Public on purpose:
// anonymous visitors of /ticker/{id} count as viewers too. The counts in the response
// are only included for signed-in coordinators and members of the ticker's team.

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/ticker_viewers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo '{}';
    exit;
}

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);
if ($ticker_id <= 0) {
    http_response_code(400);
    echo '{}';
    exit;
}

// Zuschauer = Browser-Sitzung. Die App setzt das Sitzungs-Cookie ohnehin für jeden Aufruf;
// gespeichert wird nur ein nicht umkehrbarer Hash, getrennt pro Ticker. So zählt ein Browser
// einmal, egal wie oft die Seite geöffnet wird. Ohne Cookie (blockiert) wird nicht gezählt,
// sonst ergäbe jeder Ping einen neuen Zuschauer.
$viewer_id = null;
if (!empty($_COOKIE[session_name()])) {
    // Leere Sitzungen verwirft PHP (use_strict_mode) — der Merker hält auch anonyme stabil.
    $_SESSION['ticker_viewer'] = true;
    $h = hash('sha256', 'ticker-viewer|' . $ticker_id . '|' . session_id());
    $viewer_id = substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-'
               . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}
// Session-Sperre sofort freigeben, damit parallele Seitenaufrufe nicht auf den Ping warten
session_write_close();

$pdo = get_db();

// Ticker ohne Teamkontext nachschlagen (wie ticker_detail_handler.php)
set_admin_context($pdo);
$stmt = $pdo->prepare(
    "SELECT t.id, t.team_id, t.status
     FROM tickers t JOIN teams tm ON tm.id = t.team_id AND tm.is_active = TRUE
     WHERE t.id = ?"
);
$stmt->execute([$ticker_id]);
$ticker = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ticker) {
    reset_rls_context($pdo);
    http_response_code(404);
    echo '{}';
    exit;
}
$team_id = (int)$ticker['team_id'];

// Darf der Aufrufer die Zahlen sehen? Mitglied oder Koordinator dieses Teams.
$role = $_SESSION['role'] ?? '';
$may_see = false;
if (!empty($_SESSION['user_id']) && in_array($role, ['coordinator', 'member'], true)
    && (time() - (int)($_SESSION['last_activity'] ?? 0)) <= SESSION_TIMEOUT) {
    $may_see = (int)($_SESSION['team_id'] ?? 0) === $team_id;
    if (!$may_see && $role === 'coordinator') {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM coordinator_teams WHERE user_id = ? AND team_id = ? AND left_at IS NULL"
        );
        $stmt->execute([(int)$_SESSION['user_id'], $team_id]);
        $may_see = (bool)$stmt->fetchColumn();
    }
}

// Nur laufende Ticker zählen; geschlossene behalten ihr Maximum unverändert
if ($ticker['status'] === 'active' && $viewer_id !== null) {
    reset_rls_context($pdo);
    set_team_context($pdo, $team_id);
    $counts = ticker_viewers_record($pdo, $ticker_id, $viewer_id);
} elseif ($ticker['status'] === 'active') {
    $counts = ticker_viewers_counts($pdo, $ticker_id);
} else {
    $counts = ticker_viewers_counts($pdo, $ticker_id);
    $counts['active'] = 0;
}
reset_rls_context($pdo);

$out = ['status' => $ticker['status'], 'interval' => TICKER_VIEWER_PING_SECONDS];
if ($may_see) {
    $out += $counts;
}
echo json_encode($out);
