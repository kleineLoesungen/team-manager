<?php
// src/db/guest.php — Gastbereich ohne Anmeldung (Issue #15)
//
// Gäste sehen Termine und Listen mit Datum, die ein Koordinator „Für Gäste sichtbar“ gemacht hat
// (lists.guest_visible, events.guest_visible) — nur solange der Eintrag nicht privat ist, und nur
// Titel, Ort, Datum und Zeit, nie Listen- oder Dokumentinhalte. Pro Team gibt es dazu ein
// Gast-Kalender-Abo (teams.calendar_token_guest). Ressourcen: src/db/resources.php
// (resources_for_guests), Ticker: öffentliche Ticker-Seiten. Alles läuft im Admin-Kontext,
// weil Gäste keinen Team-Kontext haben; die Filter stehen in den Abfragen selbst.

declare(strict_types=1);

require_once ROOT_PATH . '/src/utils/calendar.php';   // ICS_PAST_INTERVAL

/**
 * SQL of all entries guests may see: guest-visible, not private, active team. Columns:
 * kind, id, team_id, team_name, department_id, department_name, dept_icon, title, name (= title),
 * date, time_start, time_end, is_all_day, location, icon.
 */
function guest_items_sql(): string {
    return "
        SELECT 'list' AS kind, l.id, l.team_id, t.name AS team_name, d.id AS department_id,
               d.name AS department_name, d.icon AS dept_icon, l.name AS title, l.name,
               l.date, l.time_start, l.time_end, (l.time_start IS NULL) AS is_all_day,
               l.location, NULL AS icon, t.sort_order
        FROM lists l JOIN teams t ON t.id = l.team_id AND t.is_active = TRUE
        JOIN departments d ON d.id = t.department_id
        WHERE l.guest_visible = TRUE AND l.visibility <> 'private' AND l.date IS NOT NULL
        UNION ALL
        SELECT 'event', e.id, e.team_id, t.name, d.id, d.name, d.icon, e.title, e.title,
               e.date, e.time_start, e.time_end, (e.is_all_day OR e.time_start IS NULL),
               e.location, e.icon, t.sort_order
        FROM events e JOIN teams t ON t.id = e.team_id AND t.is_active = TRUE
        JOIN departments d ON d.id = t.department_id
        WHERE e.guest_visible = TRUE AND e.visibility <> 'private'";
}

/**
 * Upcoming entries for the guest page, from today, grouped by date: [Y-m-d => rows].
 * @param int|null $department_id Filter (null = all departments)
 */
function guest_upcoming(PDO $pdo, ?int $department_id, int $limit = 200): array {
    $rows = as_admin($pdo, function () use ($pdo, $department_id, $limit) {
        $sql  = guest_items_sql();
        $stmt = $pdo->prepare(
            "WITH g AS ($sql)
             SELECT * FROM g
             WHERE date >= (NOW() AT TIME ZONE 'Europe/Berlin')::date
               AND (?::int IS NULL OR department_id = ?::int)
             ORDER BY date, time_start NULLS FIRST, department_name, sort_order, team_name
             LIMIT " . (int)$limit
        );
        $stmt->execute([$department_id, $department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });
    $days = [];
    foreach ($rows as $r) $days[$r['date']][] = $r;
    return $days;
}

/** Departments that have guest entries from today on (for the filter). */
function guest_departments(PDO $pdo): array {
    return as_admin($pdo, function () use ($pdo) {
        $sql = guest_items_sql();
        return $pdo->query(
            "WITH g AS ($sql)
             SELECT DISTINCT department_id AS id, department_name AS name FROM g
             WHERE date >= (NOW() AT TIME ZONE 'Europe/Berlin')::date ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    });
}

/**
 * Teams with guest entries (from 3 months back on, like the feed) and their guest calendar URL.
 * Tokens are created on first use. @return list<array{team_id, team_name, department_name, url}>
 */
function guest_calendars(PDO $pdo, ?int $department_id): array {
    return as_admin($pdo, function () use ($pdo, $department_id) {
        $sql  = guest_items_sql();
        $stmt = $pdo->prepare(
            "WITH g AS ($sql)
             SELECT DISTINCT team_id, team_name, department_name, sort_order FROM g
             WHERE date >= CURRENT_DATE - INTERVAL '" . ICS_PAST_INTERVAL . "'
               AND (?::int IS NULL OR department_id = ?::int)
             ORDER BY department_name, sort_order, team_name"
        );
        $stmt->execute([$department_id, $department_id]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $tok = $pdo->prepare("SELECT calendar_token_guest FROM teams WHERE id = ?");
            $tok->execute([(int)$t['team_id']]);
            $token = (string)$tok->fetchColumn();
            if ($token === '') {
                $token = generate_calendar_token();
                $pdo->prepare("UPDATE teams SET calendar_token_guest = ? WHERE id = ? AND calendar_token_guest IS NULL")
                    ->execute([$token, (int)$t['team_id']]);
                $tok->execute([(int)$t['team_id']]);
                $token = (string)$tok->fetchColumn();
            }
            $out[] = ['team_id' => (int)$t['team_id'], 'team_name' => $t['team_name'],
                      'department_name' => $t['department_name'], 'url' => absolute_url('/ics/' . $token . '.ics')];
        }
        return $out;
    });
}

/** Guest feed of one team: lists and events for the ICS export (3 months back on). */
function guest_calendar_feed(PDO $pdo, int $team_id): array {
    return as_admin($pdo, function () use ($pdo, $team_id) {
        $sql  = guest_items_sql();
        $stmt = $pdo->prepare(
            "WITH g AS ($sql)
             SELECT * FROM g WHERE team_id = ? AND date >= CURRENT_DATE - INTERVAL '" . ICS_PAST_INTERVAL . "'
             ORDER BY date, time_start NULLS FIRST"
        );
        $stmt->execute([$team_id]);
        $lists = $events = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['kind'] === 'list') $lists[] = $r; else $events[] = $r;
        }
        return ['lists' => $lists, 'events' => $events];
    });
}
