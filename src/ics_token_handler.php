<?php
// src/ics_token_handler.php - GET /ics/{token}.ics - ICS feed, no session required
// The token is either
//   - a team's coordinator token (teams.calendar_token_coordinator): everything of the team,
//     shared by all its coordinators, or
//   - a member's personal token (members.calendar_token): the person's lists and events across
//     all their teams, filtered by each list's calendar column (src/db/calendar.php, Issue #12).

declare(strict_types=1);

require_once ROOT_PATH . '/src/utils/calendar.php';
require_once ROOT_PATH . '/src/db/calendar.php';

$raw_token = $_REQUEST['cal_token'] ?? '';

// Reject obviously invalid tokens early (must be 64 lowercase hex chars)
if (!preg_match('/^[0-9a-f]{64}$/', $raw_token)) {
    http_response_code(404);
    exit;
}

$pdo = get_db();

// Resolve the token - admin context bypasses RLS (token lookup is credential verification)
[$team_id, $member_id] = as_admin($pdo, function () use ($pdo, $raw_token) {
    $stmt = $pdo->prepare("SELECT id FROM teams WHERE calendar_token_coordinator = ? AND is_active = TRUE");
    $stmt->execute([$raw_token]);
    $team_id = $stmt->fetchColumn();
    if ($team_id !== false) return [(int)$team_id, null];

    $stmt = $pdo->prepare("SELECT id FROM members WHERE calendar_token = ? AND is_active = TRUE");
    $stmt->execute([$raw_token]);
    $member_id = $stmt->fetchColumn();
    return [null, $member_id !== false ? (int)$member_id : null];
});

if ($team_id === null && $member_id === null) {
    http_response_code(404);
    exit;
}

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

if ($team_id !== null) {
    // Coordinator feed: scoped context for the team, all visibilities
    set_team_context($pdo, $team_id, 'coordinator');

    $stmt = $pdo->prepare(
        "SELECT l.id, l.team_id, t.name AS team_name, l.name, l.date, l.location, l.description, l.time_start, l.time_end
         FROM lists l JOIN teams t ON t.id = l.team_id
         WHERE l.team_id = ? AND l.date IS NOT NULL AND l.visibility IN ('public','protected','private')
         ORDER BY l.date ASC"
    );
    $stmt->execute([$team_id]);
    $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT e.id, e.team_id, t.name AS team_name, e.title, e.description, e.location, e.icon,
                e.date, e.is_all_day, e.time_start, e.time_end
         FROM events e JOIN teams t ON t.id = e.team_id
         WHERE e.team_id = ? AND e.visibility IN ('protected','private') AND e.date IS NOT NULL
         ORDER BY e.date ASC"
    );
    $stmt->execute([$team_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'team-' . $team_id . '.ics';
    $ics      = ics_team_calendar($lists, $events, $base_url, 'coordinator');
} else {
    // Personal feed: team name in front of each entry once the person is in several teams
    $feed     = member_calendar_feed($pdo, $member_id);
    $filename = 'kalender.ics';
    $ics      = ics_team_calendar($feed['lists'], $feed['events'], $base_url, 'member', $feed['team_count'] > 1);
}
reset_rls_context($pdo);

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $ics;
exit;
