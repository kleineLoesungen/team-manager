<?php
// src/db/calendar.php — Persönlicher Kalender der Mitglieder (Issue #12)
//
// Ein Link je Person (members.calendar_token) über alle ihre Teams, ohne Login abrufbar.
// Enthält die Listen und Termine, die ein Mitglied im jeweiligen Team sehen darf
// (Listen öffentlich/geschützt, Termine geschützt). Hat eine Mitgliederliste eine
// Kalender-Spalte (lists.calendar_column_id, Ja/Nein), erscheint sie nur bei „Ja“ —
// eingetragener Wert, sonst der Standardwert der Spalte in dieser Liste, sonst „Nein“.
// Nur Spalten, die Mitglieder sehen (nicht „nur Koordinatoren“). Ist die Spalte nicht mehr
// Teil der Liste (gelöscht, entfernt), erscheint die Liste immer.

declare(strict_types=1);

require_once ROOT_PATH . '/src/utils/calendar.php';   // ICS_PAST_INTERVAL

/** Personal calendar token of a member (person); created on first use, or renewed. */
function member_calendar_token(PDO $pdo, int $member_id, bool $renew = false): string {
    return as_admin($pdo, function () use ($pdo, $member_id, $renew) {
        if (!$renew) {
            $stmt = $pdo->prepare("SELECT calendar_token FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $token = $stmt->fetchColumn();
            if (is_string($token) && $token !== '') return $token;
        }
        $token = generate_calendar_token();
        $pdo->prepare("UPDATE members SET calendar_token = ? WHERE id = ?")->execute([$token, $member_id]);
        return $token;
    });
}

/**
 * Lists and events of a member's personal calendar, across all teams the person is an
 * active member of. Runs in admin context: the token was the credential.
 * @return array{lists: array, events: array, team_count: int}
 */
function member_calendar_feed(PDO $pdo, int $member_id): array {
    return as_admin($pdo, function () use ($pdo, $member_id) {
        $accounts = "SELECT u.id AS user_id, u.team_id, t.name AS team_name, d.icon AS dept_icon
                     FROM users u JOIN teams t ON t.id = u.team_id AND t.is_active = TRUE
                     LEFT JOIN departments d ON d.id = t.department_id
                     WHERE u.member_id = ? AND u.role = 'member' AND u.is_active = TRUE";

        $stmt = $pdo->prepare(
            "WITH a AS ($accounts)
             SELECT l.id, l.team_id, a.team_name, a.dept_icon, l.name, l.date, l.location, l.description,
                    l.time_start, l.time_end
             FROM a
             JOIN lists l ON l.team_id = a.team_id
             LEFT JOIN columns c ON c.id = l.calendar_column_id
                  AND c.is_active = TRUE AND c.data_type = 'boolean' AND c.coach_only = FALSE
             LEFT JOIN list_global_columns lgc ON lgc.list_id = l.id AND lgc.column_id = c.id
             LEFT JOIN cells ce ON ce.list_id = l.id AND ce.column_id = c.id AND ce.member_id = a.user_id
             WHERE l.date >= CURRENT_DATE - INTERVAL '" . ICS_PAST_INTERVAL . "' AND l.visibility IN ('public', 'protected')
               AND (l.list_type <> 'member'
                    OR c.id IS NULL                                      -- keine (gültige) Spalte
                    OR NOT (c.list_id = l.id OR (c.list_id IS NULL AND lgc.list_id IS NOT NULL))
                    OR COALESCE(ce.value, lgc.default_value, '0') = '1')
             ORDER BY l.date, l.time_start NULLS FIRST"
        );
        $stmt->execute([$member_id]);
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "WITH a AS ($accounts)
             SELECT e.id, e.team_id, a.team_name, a.dept_icon, e.title, e.description, e.location, e.icon,
                    e.date, e.is_all_day, e.time_start, e.time_end
             FROM a JOIN events e ON e.team_id = a.team_id
             WHERE e.visibility = 'protected' AND e.date >= CURRENT_DATE - INTERVAL '" . ICS_PAST_INTERVAL . "'
             ORDER BY e.date, e.time_start NULLS FIRST"
        );
        $stmt->execute([$member_id]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ($accounts) a");
        $stmt->execute([$member_id]);
        return ['lists' => $lists, 'events' => $events, 'team_count' => (int)$stmt->fetchColumn()];
    });
}

/**
 * Boolean columns of a list that can decide about the personal calendar: linked global
 * columns (team + system) and the list's own columns, without coordinator-only ones. [id => name]
 */
function list_calendar_columns(PDO $pdo, int $list_id, int $team_id): array {
    return as_admin($pdo, function () use ($pdo, $list_id, $team_id) {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.name FROM columns c
             LEFT JOIN list_global_columns lgc ON lgc.list_id = ? AND lgc.column_id = c.id
             WHERE c.is_active = TRUE AND c.data_type = 'boolean' AND c.coach_only = FALSE
               AND ((c.list_id = ? AND c.team_id = ?)
                    OR (c.list_id IS NULL AND lgc.list_id IS NOT NULL AND (c.team_id = ? OR c.is_system = TRUE)))
             ORDER BY c.list_id NULLS FIRST, c.is_system DESC, c.sort_order, c.created_at"
        );
        $stmt->execute([$list_id, $list_id, $team_id, $team_id]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
    });
}

/**
 * Zusagen der Mitgliederlisten eines Teams mit Kalender-Spalte: [list_id => [ja, aktive Mitglieder]].
 * „Ja“ wie im persönlichen Kalender: eingetragener Wert, sonst Standardwert der Spalte, sonst Nein.
 * Listen ohne (gültige) Kalender-Spalte fehlen.
 */
function list_calendar_rsvp_counts(PDO $pdo, int $team_id): array {
    return as_admin($pdo, function () use ($pdo, $team_id) {
        $stmt = $pdo->prepare(
            "SELECT l.id,
                    COUNT(u.id) FILTER (WHERE COALESCE(ce.value, lgc.default_value, '0') = '1') AS yes,
                    COUNT(u.id) AS total
             FROM lists l
             JOIN columns c ON c.id = l.calendar_column_id
                  AND c.is_active = TRUE AND c.data_type = 'boolean' AND c.coach_only = FALSE
             LEFT JOIN list_global_columns lgc ON lgc.list_id = l.id AND lgc.column_id = c.id
             JOIN users u ON u.team_id = l.team_id AND u.role = 'member' AND u.is_active = TRUE
             LEFT JOIN cells ce ON ce.list_id = l.id AND ce.column_id = c.id AND ce.member_id = u.id
             WHERE l.team_id = ? AND l.list_type = 'member' AND l.date >= CURRENT_DATE - INTERVAL '" . ICS_PAST_INTERVAL . "'
               AND (c.list_id = l.id OR (c.list_id IS NULL AND lgc.list_id IS NOT NULL))
             GROUP BY l.id"
        );
        $stmt->execute([$team_id]);
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $counts[(int)$r['id']] = [(int)$r['yes'], (int)$r['total']];
        }
        return $counts;
    });
}
