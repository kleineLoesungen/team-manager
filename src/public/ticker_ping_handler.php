<?php
// src/public/ticker_ping_handler.php — POST /ticker/{id}/ping
// Heartbeat of an open ticker tab (see src/db/ticker_viewers.php). Public on purpose:
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

// Nur lesen, nie schreiben: Session-Sperre sofort freigeben, damit parallele Seitenaufrufe
// desselben Nutzers nicht auf den Ping warten.
session_write_close();

$ticker_id = (int)($_REQUEST['ticker_id'] ?? 0);
$viewer_id = strtolower((string)($_POST['viewer'] ?? ''));
if ($ticker_id <= 0 || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $viewer_id)) {
    http_response_code(400);
    echo '{}';
    exit;
}

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
if ($ticker['status'] === 'active') {
    reset_rls_context($pdo);
    set_team_context($pdo, $team_id);
    $counts = ticker_viewers_record($pdo, $ticker_id, $viewer_id);
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
