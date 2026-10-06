<?php
// src/db/resources.php — Ressourcen (Platz, Halle, Bus …) und ihre Belegung durch Listen und Termine
//
// Der Admin pflegt die Ressourcen für alle Teams. Koordinatoren wählen sie bei Listen und
// Terminen aus (resource_bookings). Die Zeit einer Belegung kommt immer aus Datum und Uhrzeit
// der Liste bzw. des Termins:
//   ohne Uhrzeit oder ganztägig  → der ganze Tag
//   ohne Ende                    → 1 Stunde (wie im Kalender-Export)
// Listen ohne Datum belegen nichts. Überschneidungen sind erlaubt und werden nur angezeigt.

declare(strict_types=1);

const RESOURCE_PICKER_SWITCH_MAX = 5;   // bis hier ein Schalter je Ressource, darüber Chips

/** Active resources (id, name), for the selection in forms. Works in any team context. */
function resources_active(PDO $pdo): array {
    return $pdo->query("SELECT id, name FROM resources WHERE is_active = TRUE ORDER BY name")
               ->fetchAll(PDO::FETCH_ASSOC);
}

/** Resource ids booked by one list or event (own team, RLS). */
function resources_booked_ids(PDO $pdo, string $kind, int $id): array {
    $col  = $kind === 'event' ? 'event_id' : 'list_id';
    $stmt = $pdo->prepare("SELECT resource_id FROM resource_bookings WHERE $col = ?");
    $stmt->execute([$id]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Names of the active resources booked by one list or event, for display. */
function resources_booked_names(PDO $pdo, string $kind, int $id): array {
    $col  = $kind === 'event' ? 'event_id' : 'list_id';
    $stmt = $pdo->prepare(
        "SELECT r.name FROM resource_bookings b JOIN resources r ON r.id = b.resource_id AND r.is_active = TRUE
         WHERE b.$col = ? ORDER BY r.name"
    );
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Selected resource ids from the form (resource_ids[]), as ints. */
function resources_from_post(): array {
    $ids = array_map('intval', (array)($_POST['resource_ids'] ?? []));
    return array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
}

/**
 * Replace the bookings of one list or event with $resource_ids. Inactive resources that are
 * already booked stay booked (they are not shown in the form); unknown ids are ignored.
 * Caller provides the coordinator's team context (RLS) and, if wanted, the transaction.
 */
function resources_save(PDO $pdo, int $team_id, string $kind, int $id, array $resource_ids): void {
    $col = $kind === 'event' ? 'event_id' : 'list_id';
    $pdo->prepare(
        "DELETE FROM resource_bookings
         WHERE $col = ? AND resource_id IN (SELECT id FROM resources WHERE is_active = TRUE)"
    )->execute([$id]);
    if (!$resource_ids) return;

    $active = array_column(resources_active($pdo), 'id');
    $active = array_map('intval', $active);
    $ins = $pdo->prepare(
        "INSERT INTO resource_bookings (resource_id, team_id, $col) VALUES (?, ?, ?)
         ON CONFLICT DO NOTHING"
    );
    foreach ($resource_ids as $rid) {
        if (in_array($rid, $active, true)) $ins->execute([$rid, $team_id, $id]);
    }
}

/**
 * SQL for all dated bookings of active resources and teams with their time window (starts_at/ends_at as
 * timestamps) and what a viewer may learn about them. Read in admin context only.
 * Columns: booking_id, resource_id, resource_name, team_id, team_name, kind, item_id, title,
 *          shared (visible to the owning team's members), date, time_start, time_end,
 *          all_day, starts_at, ends_at
 */
function resources_slots_sql(): string {
    return "
        SELECT b.id AS booking_id, b.resource_id, r.name AS resource_name,
               b.team_id, tm.name AS team_name, 'list' AS kind, l.id AS item_id, l.name AS title,
               (l.visibility IN ('public', 'protected')) AS shared,
               l.date, l.time_start, l.time_end, (l.time_start IS NULL) AS all_day,
               l.date + COALESCE(l.time_start, TIME '00:00') AS starts_at,
               CASE WHEN l.time_start IS NULL THEN l.date + INTERVAL '1 day'
                    WHEN l.time_end > l.time_start THEN l.date + l.time_end
                    ELSE l.date + l.time_start + INTERVAL '1 hour' END AS ends_at
        FROM resource_bookings b
        JOIN resources r ON r.id = b.resource_id AND r.is_active = TRUE
        JOIN lists l     ON l.id = b.list_id AND l.date IS NOT NULL
        JOIN teams tm    ON tm.id = b.team_id AND tm.is_active = TRUE
        UNION ALL
        SELECT b.id, b.resource_id, r.name,
               b.team_id, tm.name, 'event', e.id, e.title,
               (e.visibility = 'protected'),
               e.date, e.time_start, e.time_end, (e.is_all_day OR e.time_start IS NULL),
               e.date + CASE WHEN e.is_all_day OR e.time_start IS NULL THEN TIME '00:00' ELSE e.time_start END,
               CASE WHEN e.is_all_day OR e.time_start IS NULL THEN e.date + INTERVAL '1 day'
                    WHEN e.time_end > e.time_start THEN e.date + e.time_end
                    ELSE e.date + e.time_start + INTERVAL '1 hour' END
        FROM resource_bookings b
        JOIN resources r ON r.id = b.resource_id AND r.is_active = TRUE
        JOIN events e    ON e.id = b.event_id
        JOIN teams tm    ON tm.id = b.team_id AND tm.is_active = TRUE";
}

/** Run $fn in admin context and restore the signed-in user's team context afterwards. */
function resources_as_admin(PDO $pdo, callable $fn): mixed {
    set_admin_context($pdo);
    try {
        return $fn();
    } finally {
        reset_rls_context($pdo);
        if (!empty($_SESSION['team_id'])) {
            set_team_context($pdo, (int)$_SESSION['team_id'], $_SESSION['role'] ?? null,
                             isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        }
    }
}

/**
 * Title as a viewer may see it: own team — what their role sees; other teams — what that
 * team's members see. Everything else is just "Belegt". 'url' only for the viewer's own team.
 */
function resources_present_slot(array $row, int $viewer_team, string $viewer_role): array {
    $shared   = in_array($row['shared'], [true, 1, '1', 't', 'true'], true);
    $own      = (int)$row['team_id'] === $viewer_team;
    $readable = $shared || ($own && $viewer_role === 'coordinator');
    $row['all_day'] = in_array($row['all_day'], [true, 1, '1', 't', 'true'], true);
    $row['label']   = $readable ? $row['title'] : 'Belegt';
    $row['url']     = null;
    if ($own && $readable) {
        $base = $viewer_role === 'coordinator' ? '/coordinator' : '/member';
        $row['url'] = $row['kind'] === 'list'
            ? $base . '/lists/' . (int)$row['item_id']
            : ($viewer_role === 'coordinator' ? '/coordinator/events/' . (int)$row['item_id'] . '/edit' : null);
    }
    return $row;
}

/** "18:00–19:30", "ab 18:00" or "ganztägig" for a slot row. */
function resources_slot_time(array $row): string {
    if ($row['all_day']) return 'ganztägig';
    $start = substr((string)$row['time_start'], 0, 5);
    $end   = $row['time_end'] ? substr((string)$row['time_end'], 0, 5) : null;
    return $end ? $start . '–' . $end : 'ab ' . $start;
}

/**
 * Bookings of other lists/events that overlap the given list or event, any team.
 * @return list<array> presented slot rows (resources_present_slot) of the OTHER bookings
 */
function resources_conflicts(PDO $pdo, string $kind, int $id): array {
    $rows = resources_as_admin($pdo, function () use ($pdo, $kind, $id) {
        $slots = resources_slots_sql();
        $stmt  = $pdo->prepare(
            "WITH s AS ($slots)
             SELECT o.* FROM s me
             JOIN s o ON o.resource_id = me.resource_id AND o.booking_id <> me.booking_id
                     AND NOT (o.kind = me.kind AND o.item_id = me.item_id)
                     AND o.starts_at < me.ends_at AND o.ends_at > me.starts_at
             WHERE me.kind = ? AND me.item_id = ?
             ORDER BY o.starts_at, o.resource_name"
        );
        $stmt->execute([$kind, $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });
    $team = (int)($_SESSION['team_id'] ?? 0);
    $role = (string)($_SESSION['role'] ?? 'member');
    return array_map(fn($r) => resources_present_slot($r, $team, $role), $rows);
}

/**
 * Check before saving: which bookings of the given resources overlap a planned time window
 * on each of $dates (a list, a series or an event, not yet saved). Same window rules as
 * resources_slots_sql(). The item being edited ($exclude_kind/$exclude_id) is left out.
 * @param int[]    $resource_ids
 * @param string[] $dates Y-m-d
 * @return list<array> presented slot rows (resources_present_slot), ordered by time
 */
function resources_check(PDO $pdo, array $resource_ids, array $dates, ?string $start, ?string $end,
                         bool $all_day, ?string $exclude_kind = null, ?int $exclude_id = null): array {
    $resource_ids = array_values(array_filter(array_map('intval', $resource_ids)));
    $dates        = array_values(array_filter($dates, fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)));
    if (!$resource_ids || !$dates) return [];
    $start = ($all_day || !$start || !preg_match('/^\d{2}:\d{2}/', $start)) ? null : substr($start, 0, 5);
    $end   = ($start && $end && preg_match('/^\d{2}:\d{2}/', $end)) ? substr($end, 0, 5) : null;

    $rows = resources_as_admin($pdo, function () use ($pdo, $resource_ids, $dates, $start, $end, $exclude_kind, $exclude_id) {
        $slots = resources_slots_sql();
        $stmt  = $pdo->prepare(
            "WITH s AS ($slots),
             w AS (
                 SELECT d + COALESCE(CAST(:start AS time), TIME '00:00') AS w_start,
                        CASE WHEN CAST(:start2 AS time) IS NULL THEN d + INTERVAL '1 day'
                             WHEN CAST(:end AS time) > CAST(:start3 AS time) THEN d + CAST(:end2 AS time)
                             ELSE d + CAST(:start4 AS time) + INTERVAL '1 hour' END AS w_end
                 FROM unnest(CAST(:dates AS date[])) AS d
             )
             SELECT DISTINCT s.* FROM s JOIN w ON s.starts_at < w.w_end AND s.ends_at > w.w_start
             WHERE s.resource_id = ANY(CAST(:ids AS int[]))
               AND NOT (s.kind = :kind AND s.item_id = :item)
             ORDER BY s.starts_at, s.resource_name"
        );
        $stmt->execute([
            ':start' => $start, ':start2' => $start, ':start3' => $start, ':start4' => $start,
            ':end' => $end, ':end2' => $end,
            ':dates' => '{' . implode(',', $dates) . '}',
            ':ids'   => '{' . implode(',', $resource_ids) . '}',
            ':kind'  => (string)$exclude_kind, ':item' => (int)$exclude_id,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });
    $team = (int)($_SESSION['team_id'] ?? 0);
    $role = (string)($_SESSION['role'] ?? 'member');
    return array_map(fn($r) => resources_present_slot($r, $team, $role), $rows);
}

/** How many of the given lists/events overlap another booking (for the message after saving). */
function resources_conflict_count(PDO $pdo, string $kind, array $ids): int {
    if (!$ids) return 0;
    return (int)resources_as_admin($pdo, function () use ($pdo, $kind, $ids) {
        $slots = resources_slots_sql();
        $in    = implode(',', array_fill(0, count($ids), '?'));
        $stmt  = $pdo->prepare(
            "WITH s AS ($slots)
             SELECT COUNT(DISTINCT me.item_id) FROM s me
             JOIN s o ON o.resource_id = me.resource_id AND o.booking_id <> me.booking_id
                     AND NOT (o.kind = me.kind AND o.item_id = me.item_id)
                     AND o.starts_at < me.ends_at AND o.ends_at > me.starts_at
             WHERE me.kind = ? AND me.item_id IN ($in)"
        );
        $stmt->execute(array_merge([$kind], array_map('intval', $ids)));
        return $stmt->fetchColumn();
    });
}

/**
 * Usage of all (or one) resource from $from (Y-m-d) on, all teams, grouped by date.
 * Each row is presented for the viewer and carries 'overlap' (another booking at that time).
 * @return array<string, list<array>> date => rows, ordered by time
 */
function resources_usage(PDO $pdo, ?int $resource_id, string $from, int $limit = 300): array {
    $rows = resources_as_admin($pdo, function () use ($pdo, $resource_id, $from, $limit) {
        $slots  = resources_slots_sql();
        $params = [$from];
        $filter = '';
        if ($resource_id !== null) { $filter = ' AND me.resource_id = ?'; $params[] = $resource_id; }
        $stmt = $pdo->prepare(
            "WITH s AS ($slots)
             SELECT me.*, EXISTS (
                 SELECT 1 FROM s o
                 WHERE o.resource_id = me.resource_id AND o.booking_id <> me.booking_id
                   AND NOT (o.kind = me.kind AND o.item_id = me.item_id)
                   AND o.starts_at < me.ends_at AND o.ends_at > me.starts_at
             ) AS overlap
             FROM s me
             WHERE me.ends_at > ?::date$filter
             ORDER BY me.starts_at, me.resource_name, me.team_name
             LIMIT " . (int)$limit
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });
    $team = (int)($_SESSION['team_id'] ?? 0);
    $role = (string)($_SESSION['role'] ?? 'member');
    $days = [];
    foreach ($rows as $r) {
        $r = resources_present_slot($r, $team, $role);
        $r['overlap'] = in_array($r['overlap'], [true, 1, '1', 't', 'true'], true);
        $days[$r['date']][] = $r;
    }
    return $days;
}

/** Calendar token of a resource, created on first use. Admin context required to create. */
function resources_calendar_token(PDO $pdo, int $resource_id): ?string {
    return resources_as_admin($pdo, function () use ($pdo, $resource_id) {
        $stmt = $pdo->prepare("SELECT calendar_token FROM resources WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$resource_id]);
        $token = $stmt->fetchColumn();
        if ($token === false) return null;
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE resources SET calendar_token = ? WHERE id = ? AND calendar_token IS NULL")
                ->execute([$token, $resource_id]);
            $stmt->execute([$resource_id]);
            $token = $stmt->fetchColumn();
        }
        return trim((string)$token);
    });
}
