<?php
// src/db/dashboard.php — Daten für die Übersicht (Reiter "Inhalte", Ansicht "Übersicht")
//
// Ersetzt die frühere Wochenansicht. Drei Bereiche, alle aus vorhandenen Daten:
//   Live            laufende Ticker des Teams und der weiteren Teams des Nutzers, mit letztem
//                   Eintrag; darunter ein Link auf /ticker (Ticker aller Teams)
//   Nächste 7 Tage  Listen, Dokumente, Termine und geplante Ticker; Mitglieder sehen bei
//                   Listen ihre eigenen Werte ("Training: Ja · Tore: 0")
//   Ohne Datum      Listen und Dokumente ohne Datum, die nicht versteckt sind
//   Deine Werte     eigene Kennzahlen (nur Mitglieder, src/db/member_stats.php)
// Sichtbarkeit wie überall: Mitglieder nur öffentliche/geschützte Listen und Dokumente,
// geschützte Termine; Koordinatoren alles ihres Teams.

declare(strict_types=1);

const DASHBOARD_DAYS = 7;
const DASHBOARD_VALUES_SHOWN = 4;   // weitere Spalten werden als "+n" zusammengefasst

/**
 * Running tickers (active and started) with their latest entry: own team first, then the
 * user's other teams — members: teams where the same member profile is an active member,
 * coordinators: further teams they manage (same rules as the ticker pages).
 * Each row gets 'team_name' (null for the own team) and 'url': coordinators open every
 * ticker in their area; members open other teams' tickers on the public page, because
 * /member/ticker/{id} only serves the team they are signed in with.
 * Uses admin context for the cross-team part and restores the role's team context.
 */
function dashboard_live_tickers(PDO $pdo, string $role, int $team_id, int $user_id): array {
    $is_coord = $role === 'coordinator';
    $live = "t.status = 'active'
             AND (t.event_date IS NULL
                  OR ((t.event_date + COALESCE(t.start_time, TIME '00:00')) AT TIME ZONE 'Europe/Berlin') <= NOW())";
    $other_teams = $is_coord
        ? "SELECT ct.team_id FROM coordinator_teams ct WHERE ct.user_id = :u AND ct.left_at IS NULL"
        : "SELECT u2.team_id FROM users u2
           WHERE u2.member_id = (SELECT member_id FROM users WHERE id = :u)
             AND u2.role = 'member' AND u2.is_active = TRUE";

    set_admin_context($pdo);
    $stmt = $pdo->prepare(
        "SELECT t.id, t.name, (t.team_id <> :own) AS other_team, tm.name AS team_name,
                m.timestamp AS last_time, m.message AS last_message, tg.label AS last_tag
         FROM tickers t
         JOIN teams tm ON tm.id = t.team_id AND tm.is_active = TRUE
         LEFT JOIN LATERAL (
             SELECT timestamp, message, tag_id FROM ticker_messages
             WHERE ticker_id = t.id ORDER BY created_at DESC LIMIT 1
         ) m ON TRUE
         LEFT JOIN ticker_tags tg ON tg.id = m.tag_id
         WHERE $live
           AND (t.team_id = :own2 OR t.team_id IN ($other_teams))
         ORDER BY (t.team_id <> :own3), tm.name, t.created_at DESC"
    );
    $stmt->execute([':own' => $team_id, ':own2' => $team_id, ':own3' => $team_id, ':u' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    reset_rls_context($pdo);
    set_team_context($pdo, $team_id, $role, $user_id);

    foreach ($rows as &$r) {
        $other = in_array($r['other_team'], [true, 1, '1', 't', 'true'], true);
        $r['team_name'] = $other ? $r['team_name'] : null;
        $r['url'] = ($is_coord || !$other)
            ? '/' . ($is_coord ? 'coordinator' : 'member') . '/ticker/' . (int)$r['id']
            : '/ticker/' . (int)$r['id'];
    }
    return $rows;
}

/**
 * Everything dated today .. today+6 (Europe/Berlin), grouped by date, sorted by time.
 * @return array<string, list<array>> date (Y-m-d) => items; item 'type' is list|file|event|ticker
 */
function dashboard_upcoming(PDO $pdo, int $team_id, bool $is_coordinator): array {
    $tz    = new DateTimeZone('Europe/Berlin');
    $from  = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
    $to    = (new DateTimeImmutable('today', $tz))->modify('+' . (DASHBOARD_DAYS - 1) . ' days')->format('Y-m-d');
    $vis   = $is_coordinator ? "('public', 'protected', 'private')" : "('public', 'protected')";
    $evvis = $is_coordinator ? "('protected', 'private')" : "('protected')";

    $stmt = $pdo->prepare(
        "SELECT 'list' AS type, id, name, date, time_start, time_end, location, visibility, list_type,
                auto_visibility, auto_visibility_hours, auto_visibility_done_at, NULL AS icon
         FROM lists WHERE team_id = :t AND visibility IN $vis AND date BETWEEN :from AND :to
         UNION ALL
         SELECT 'file', id, name, date, NULL, NULL, NULL, visibility, NULL, NULL, 0, NULL, NULL
         FROM files WHERE team_id = :t AND visibility IN $vis AND date BETWEEN :from AND :to
         UNION ALL
         SELECT 'event', id, title, date, CASE WHEN is_all_day THEN NULL ELSE time_start END,
                CASE WHEN is_all_day THEN NULL ELSE time_end END, location, visibility, NULL, NULL, 0, NULL, icon
         FROM events WHERE team_id = :t AND visibility IN $evvis AND date BETWEEN :from AND :to
         UNION ALL
         SELECT 'ticker', id, name, event_date, start_time, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL
         FROM tickers WHERE team_id = :t AND status = 'active' AND event_date BETWEEN :from AND :to
           AND ((event_date + COALESCE(start_time, TIME '00:00')) AT TIME ZONE 'Europe/Berlin') > NOW()
         ORDER BY date, time_start NULLS FIRST, name"
    );
    $stmt->execute([':t' => $team_id, ':from' => $from, ':to' => $to]);

    $by_day = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $by_day[$row['date']][] = $row;
    }
    return $by_day;
}

/**
 * The member's own values in the given member lists, only columns the member may see
 * (same filter as src/member/list_detail_handler.php). Needs admin context (system columns).
 * @param int[] $list_ids
 * @return array<int, list<array{name: string, value: string}>> list id => values in column order
 */
function dashboard_own_values(PDO $pdo, int $team_id, int $user_id, array $list_ids): array {
    if (!$list_ids) return [];
    $in = implode(',', array_fill(0, count($list_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT l.id AS list_id, c.name, c.data_type, cells.value
         FROM lists l
         JOIN columns c ON c.is_active = TRUE AND (
                  (c.list_id = l.id AND c.coach_only = FALSE)
               OR (c.list_id IS NULL AND (c.team_id = ? OR c.is_system = TRUE)
                   AND EXISTS (SELECT 1 FROM list_global_columns lgc WHERE lgc.list_id = l.id AND lgc.column_id = c.id)))
         LEFT JOIN cells ON cells.list_id = l.id AND cells.column_id = c.id AND cells.member_id = ?
         WHERE l.id IN ($in) AND l.team_id = ? AND l.list_type = 'member'
           AND l.visibility IN ('public', 'protected')
         ORDER BY l.id, (c.list_id IS NULL) DESC, c.sort_order, c.created_at"
    );
    $stmt->execute(array_merge([$team_id, $user_id], array_map('intval', $list_ids), [$team_id]));

    $values = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $values[(int)$row['list_id']][] = [
            'name'  => $row['name'],
            'type'  => $row['data_type'],
            'set'   => $row['value'] !== null && $row['value'] !== '',
            'yes'   => in_array($row['value'], ['1', 'true'], true),
            'value' => dashboard_format_value($row['data_type'], $row['value']),
        ];
    }
    return $values;
}

/**
 * Lists and documents without a date that are not hidden ("verstecken"), for the section
 * "Ohne Datum" below the next days. Same row shape as dashboard_upcoming().
 * Members: public/protected, coordinators: all of their team.
 */
function dashboard_undated(PDO $pdo, int $team_id, bool $is_coordinator): array {
    $vis  = $is_coordinator ? "('public', 'protected', 'private')" : "('public', 'protected')";
    $stmt = $pdo->prepare(
        "SELECT 'list' AS type, id, name, date, time_start, time_end, location, visibility, list_type,
                auto_visibility, auto_visibility_hours, auto_visibility_done_at, NULL AS icon
         FROM lists WHERE team_id = :t AND date IS NULL AND is_hidden = FALSE AND visibility IN $vis
         UNION ALL
         SELECT 'file', id, name, NULL, NULL, NULL, NULL, visibility, NULL, NULL, 0, NULL, NULL
         FROM files WHERE team_id = :t AND date IS NULL AND is_hidden = FALSE AND visibility IN $vis
         ORDER BY name"
    );
    $stmt->execute([':t' => $team_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dashboard_format_value(string $type, ?string $value): string {
    if ($value === null || $value === '') {
        return $type === 'boolean' ? 'Nein' : '–';
    }
    return match ($type) {
        'boolean' => in_array($value, ['1', 'true'], true) ? 'Ja' : 'Nein',
        'number'  => is_numeric($value)
            ? (floor((float)$value) == (float)$value ? (string)(int)$value : number_format((float)$value, 2, ',', '.'))
            : $value,
        default   => mb_strimwidth($value, 0, 30, '…'),
    };
}

/** "Heute", "Morgen", otherwise "Mi 08.10." */
function dashboard_day_label(string $date): string {
    $tz  = new DateTimeZone('Europe/Berlin');
    $day = new DateTimeImmutable($date, $tz);
    $today = new DateTimeImmutable('today', $tz);
    $diff  = (int)$today->diff($day)->format('%r%a');
    if ($diff === 0) return 'Heute';
    if ($diff === 1) return 'Morgen';
    return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)$day->format('w')] . ' ' . $day->format('d.m.');
}

/**
 * Everything the overview needs for one role. Leaves the RLS context as the role's team
 * context (set_team_context) for the rest of the request.
 */
function dashboard_data(PDO $pdo, string $role): array {
    $team_id = (int)$_SESSION['team_id'];
    $user_id = (int)$_SESSION['user_id'];
    $is_coordinator = $role === 'coordinator';

    $data = [
        'live'     => dashboard_live_tickers($pdo, $role, $team_id, $user_id),
        'upcoming' => dashboard_upcoming($pdo, $team_id, $is_coordinator),
        'undated'  => dashboard_undated($pdo, $team_id, $is_coordinator),
        'values'   => [],
        'columns'  => [],
        'totals'   => [],
    ];

    if (!$is_coordinator) {
        $list_ids = [];
        foreach (array_merge($data['undated'], ...array_values($data['upcoming'])) as $it) {
            if ($it['type'] === 'list' && $it['list_type'] === 'member') $list_ids[] = (int)$it['id'];
        }
        require_once ROOT_PATH . '/src/db/member_stats.php';
        set_admin_context($pdo);   // Systemspalten
        $data['values']  = dashboard_own_values($pdo, $team_id, $user_id, $list_ids);
        $data['columns'] = member_stats_global_columns($pdo, $team_id);
        $data['totals']  = $data['columns'] ? member_stats_totals($pdo, $team_id, $user_id) : [];
        reset_rls_context($pdo);
        set_team_context($pdo, $team_id, 'member', $user_id);
    }
    return $data;
}
