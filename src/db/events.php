<?php
// src/db/events.php — Termine: gemeinsame Logik für Koordinatoren und Mitglieder
//
// Koordinatoren legen Termine (auch als Serie) an und ändern alle Termine ihres Teams.
// Mitglieder dürfen das nur, wenn das Team es erlaubt (teams.members_create_events), und nur
// für ihre eigenen Termine (events.created_by): ohne Serie, immer für das Team sichtbar
// (protected), nie versteckt. Die RLS-Regeln für events und resource_bookings prüfen dasselbe.

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/resources.php';

/** Whether members of the team may create events (coordinator setting). */
function events_members_may_create(PDO $pdo, int $team_id): bool {
    $stmt = $pdo->prepare("SELECT members_create_events FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    return in_array($stmt->fetchColumn(), [true, 1, '1', 't', 'true'], true);
}

/** One event of the team with the creator's name (creator_name, null if unknown/deleted). */
function event_load(PDO $pdo, int $event_id, int $team_id): ?array {
    $stmt = $pdo->prepare(
        "SELECT e.*, NULLIF(TRIM(CONCAT(m.first_name, ' ', m.last_name)), '') AS creator_name, u.role AS creator_role
         FROM events e
         LEFT JOIN users u   ON u.id = e.created_by
         LEFT JOIN members m ON m.id = u.member_id
         WHERE e.id = ? AND e.team_id = ?"
    );
    $stmt->execute([$event_id, $team_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** May this member change the event? Own event and the team allows member events. */
function event_member_may_edit(PDO $pdo, array $event, int $user_id): bool {
    return (int)($event['created_by'] ?? 0) === $user_id
        && events_members_may_create($pdo, (int)$event['team_id']);
}

/**
 * Form input of the event form, validated. Members get no series, no visibility choice and
 * no "verstecken". Without a start time the event is all-day.
 * @return array{fields: array, dates: list<string>, error: string}
 */
function event_input(bool $member, bool $allow_series): array {
    $date       = trim($_POST['date'] ?? '');
    $time_start = preg_match('/^\d{2}:\d{2}$/', trim($_POST['time_start'] ?? '')) ? trim($_POST['time_start']) : '';
    $time_end   = preg_match('/^\d{2}:\d{2}$/', trim($_POST['time_end'] ?? '')) ? trim($_POST['time_end']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location    = mb_substr(trim($_POST['location'] ?? ''), 0, 255);
    $icon        = trim($_POST['icon'] ?? '');

    $fields = [
        'title'       => $title,
        'description' => $description !== '' ? $description : null,
        'location'    => $location !== '' ? $location : null,
        'icon'        => preg_match('/^bi-[a-z0-9-]+$/', $icon) ? $icon : 'bi-calendar-event',
        'is_all_day'  => $time_start === '',
        'time_start'  => $time_start !== '' ? $time_start : null,
        'time_end'    => ($time_start !== '' && $time_end !== '') ? $time_end : null,
        'visibility'  => (!$member && ($_POST['visibility'] ?? '') === 'private') ? 'private' : 'protected',
        'is_hidden'   => !$member && ($_POST['is_hidden'] ?? '') === '1',
        // Gastbereich (Issue #15): nur Koordinatoren; null = bei Mitgliedern unverändert lassen
        'guest_visible' => $member ? null : !empty($_POST['guest_visible']),
        'date'        => $date,
    ];
    $series = ($allow_series && !$member) ? series_from_post($date, 'der Termin')
                                          : ['dates' => [$date], 'error' => ''];
    $error = match (true) {
        $title === '' || mb_strlen($title) > 200 => 'Titel erforderlich (max. 200 Zeichen).',
        $date === ''                             => 'Datum erforderlich.',
        default                                  => $series['error'],
    };
    return ['fields' => $fields, 'dates' => $series['dates'], 'error' => $error];
}

/**
 * Create one event per date (a series for coordinators) with the selected resources.
 * Caller sets the team context (RLS). Returns the new ids.
 * @param list<string> $dates
 * @return list<int>
 */
function event_create(PDO $pdo, int $team_id, int $user_id, array $f, array $dates, array $resource_ids): array {
    $stmt = $pdo->prepare(
        "INSERT INTO events (team_id, title, description, location, icon, date, is_all_day, time_start, time_end,
                             visibility, is_hidden, guest_visible, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
    );
    $created = [];
    $pdo->beginTransaction();
    foreach ($dates as $date) {
        $stmt->execute([
            $team_id, $f['title'], $f['description'], $f['location'], $f['icon'], $date,
            $f['is_all_day'] ? 'true' : 'false', $f['time_start'], $f['time_end'],
            $f['visibility'], $f['is_hidden'] ? 'true' : 'false', !empty($f['guest_visible']) ? 'true' : 'false', $user_id,
        ]);
        $id        = (int)$stmt->fetchColumn();
        $created[] = $id;
        resources_save($pdo, $team_id, 'event', $id, $resource_ids);
    }
    $pdo->commit();
    return $created;
}

/** Save an existing event and its resources. RLS limits members to their own events. */
function event_update(PDO $pdo, int $team_id, int $event_id, array $f, array $resource_ids): void {
    $pdo->prepare(
        "UPDATE events SET title = ?, description = ?, location = ?, icon = ?, date = ?, is_all_day = ?,
                           time_start = ?, time_end = ?, visibility = ?, is_hidden = ?,
                           guest_visible = COALESCE(?::boolean, guest_visible)
         WHERE id = ? AND team_id = ?"
    )->execute([
        $f['title'], $f['description'], $f['location'], $f['icon'], $f['date'],
        $f['is_all_day'] ? 'true' : 'false', $f['time_start'], $f['time_end'],
        $f['visibility'], $f['is_hidden'] ? 'true' : 'false',
        $f['guest_visible'] === null ? null : ($f['guest_visible'] ? 'true' : 'false'), $event_id, $team_id,
    ]);
    resources_save($pdo, $team_id, 'event', $event_id, $resource_ids);
}

/** Where to go after saving/deleting: the overview view the user came from (validated). */
function event_back_url(string $role): string {
    $base = $role === 'member' ? '/member/contents' : '/coordinator/contents';
    $back = (string)($_POST['_back'] ?? '');
    return preg_match('#^' . preg_quote($base, '#') . '(\?[^<>"\']*)?$#', $back) ? $back : $base;
}

/** Redirect target after saving: back URL plus success/series/conflict parameters. */
function event_saved_redirect(PDO $pdo, string $role, array $ids, array $resource_ids, bool $created): never {
    $back      = event_back_url($role);
    $conflicts = $resource_ids ? resources_conflict_count($pdo, 'event', $ids) : 0;
    $done      = ($created && count($ids) > 1) ? 'success=series&count=' . count($ids) : 'success=1';
    redirect($back . (str_contains($back, '?') ? '&' : '?') . $done . ($conflicts ? '&conflicts=' . $conflicts : ''));
}
