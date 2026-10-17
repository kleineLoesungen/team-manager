<?php
// src/push/auto_push.php — Automatische Push-Benachrichtigungen (Issue #13)
//
// 1. Erinnerung vor der automatischen Statusumstellung einer Liste (typisch: Anmeldeschluss).
//    Immer, für jede Liste mit Umstellung; genau einmal (lists.auto_reminder_sent_at), an alle
//    Mitglieder des Teams mit Push — nicht nur an die ohne Eintrag: bei Standardwerten ist
//    „nichts eingetragen“ nicht erkennbar. Zeitpunkt: am Tag der Umstellung ab LIST_REMINDER_HOUR
//    (8 Uhr); liegt die Umstellung davor, am Vortag ab LIST_REMINDER_EVENING_HOUR (18 Uhr).
//    Verschickt vom Cronjob (src/cron.php), der stündlich läuft.
// 2. Kurzfristige Änderung: Ändert ein Koordinator Datum, Uhrzeit oder Ort einer Liste oder eines
//    Termins in den nächsten CHANGE_PUSH_DAYS Tagen, kann er beim Speichern „Mitglieder per Push
//    informieren“ einschalten. Empfänger nach Sichtbarkeit (privat → Koordinatoren).

declare(strict_types=1);

require_once ROOT_PATH . '/src/push/notify_push.php';
require_once ROOT_PATH . '/src/db/list_auto_visibility.php';

const CHANGE_PUSH_DAYS      = 7;

const LIST_REMINDER_HOUR         = 8;    // am Tag der Umstellung ab 8 Uhr …
const LIST_REMINDER_EVENING_HOUR = 18;   // … oder, wenn sie vor 8 Uhr liegt, am Vortag ab 18 Uhr

/**
 * Send due deadline reminders (cron, hourly). Claims each list atomically, so parallel runs
 * cannot send twice. Returns how many lists were reminded.
 */
function list_reminders_send(PDO $pdo): int {
    set_admin_context($pdo);
    // Umstellung (deutsche Ortszeit, ohne Zeitzone) und daraus der Erinnerungszeitpunkt
    $due = "((l.date + COALESCE(l.time_start, TIME '00:00')) - make_interval(hours => l.auto_visibility_hours))";
    $at  = "(CASE WHEN $due::time >= make_time(?, 0, 0)
                  THEN $due::date + make_time(?, 0, 0)
                  ELSE $due::date - 1 + make_time(?, 0, 0) END)";
    $stmt = $pdo->prepare(
        "UPDATE lists l SET auto_reminder_sent_at = NOW()
         FROM teams t
         WHERE t.id = l.team_id AND t.is_active = TRUE
           AND l.auto_reminder_sent_at IS NULL
           AND l.auto_visibility IS NOT NULL AND l.auto_visibility_done_at IS NULL
           AND l.auto_visibility <> l.visibility
           AND l.visibility IN ('public', 'protected') AND l.date IS NOT NULL
           AND $at AT TIME ZONE 'Europe/Berlin' <= NOW()
           AND $due AT TIME ZONE 'Europe/Berlin' > NOW()
         RETURNING l.id, l.team_id, t.name AS team_name, l.name, l.visibility, l.auto_visibility,
                   l.auto_visibility_hours, l.date, l.time_start"
    );
    $stmt->execute([LIST_REMINDER_HOUR, LIST_REMINDER_HOUR, LIST_REMINDER_EVENING_HOUR]);
    $due_lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    reset_rls_context($pdo);

    $sent = 0;
    foreach ($due_lists as $list) {
        $text = list_reminder_text($list);
        if ($text === null) continue;
        push_send_now($pdo, push_team_user_ids($pdo, (int)$list['team_id'], 'member'),
                      $list['team_name'] . ' - ' . $list['name'], $text,
                      notify_push_url((int)$list['team_id'], 'list', (int)$list['id']));
        $sent++;
    }
    return $sent;
}

/** Reminder text by what the change means for members, or null if it means nothing to them. */
function list_reminder_text(array $list): ?string {
    $due = list_auto_visibility_due($list);
    if (!$due) return null;
    $when = list_auto_visibility_short_when($due);
    return match (true) {
        $list['auto_visibility'] === 'protected' && $list['visibility'] === 'public' => "Eintragen nur noch bis $when.",
        $list['auto_visibility'] === 'public'                                        => "Eintragen ab $when möglich.",
        $list['auto_visibility'] === 'private'                                       => "Die Liste wird $when ausgeblendet.",
        default                                                                      => null,
    };
}

/** Whether a date (Y-m-d) lies within the next CHANGE_PUSH_DAYS days, today included. */
function change_push_window(?string $date): bool {
    if (!$date) return false;
    $tz    = new DateTimeZone('Europe/Berlin');
    $today = new DateTimeImmutable('today', $tz);
    $d     = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
    return $d && $d >= $today && $d <= $today->modify('+' . CHANGE_PUSH_DAYS . ' days');
}

/** "Sa 11.10., 18:30" (date, optional start time). */
function change_push_when(?string $date, ?string $time): string {
    if (!$date) return 'ohne Datum';
    $d = new DateTimeImmutable($date);
    return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)$d->format('w')] . ' ' . $d->format('d.m.')
         . ($time ? ', ' . substr($time, 0, 5) : '');
}

/**
 * What changed for the push text: "Neu: Sa 11.10., 18:30 statt 18:00 · Ort: Halle 2".
 * Compares date, start/end time and place; null when none of them changed.
 * @param array $old, $new Rows with date, time_start, time_end, location
 */
function change_push_summary(array $old, array $new): ?string {
    $t = fn($v) => $v ? substr((string)$v, 0, 5) : '';
    $parts = [];
    $date_changed = (string)$old['date'] !== (string)$new['date'];
    $time_changed = $t($old['time_start']) !== $t($new['time_start']) || $t($old['time_end']) !== $t($new['time_end']);
    if ($date_changed) {
        $parts[] = 'Neu: ' . change_push_when($new['date'], $new['time_start'])
                 . ' statt ' . change_push_when($old['date'], $old['time_start']);
    } elseif ($time_changed) {
        $range = fn($r) => $t($r['time_start']) === '' ? 'ganztägig' : $t($r['time_start']) . ($t($r['time_end']) !== '' ? '–' . $t($r['time_end']) : '');
        $parts[] = 'Neu: ' . $range($new) . ' statt ' . $range($old);
    }
    if (trim((string)$old['location']) !== trim((string)$new['location'])) {
        $parts[] = 'Ort: ' . (trim((string)$new['location']) !== '' ? trim((string)$new['location']) : 'offen');
    }
    return $parts ? implode(' · ', $parts) : null;
}

/**
 * After saving a list or event: push the change if the coordinator asked for it, something
 * relevant changed and the old or new date is soon. Recipients by visibility (private →
 * coordinators, else members), not the person who saved. Returns how many accounts it went to.
 * @param string $type 'list' | 'event'
 */
function change_push_after_save(PDO $pdo, int $team_id, string $type, int $id, string $name,
                                string $visibility, array $old, array $new): int {
    if (empty($_POST['change_push'])) return 0;
    if (!change_push_window($old['date'] ?? null) && !change_push_window($new['date'] ?? null)) return 0;
    $summary = change_push_summary($old, $new);
    if ($summary === null) return 0;

    $role   = $visibility === 'private' ? 'coordinator' : 'member';
    $me     = (int)($_SESSION['user_id'] ?? 0);
    $users  = array_values(array_filter(push_team_user_ids($pdo, $team_id, $role), fn($u) => $u !== $me));
    $team   = (string)($_SESSION['team_name'] ?? '');
    push_send_to_users($pdo, $users, ($team !== '' ? $team . ' - ' : '') . $name . ' geändert', $summary,
                       notify_push_url($team_id, $type, $id));
    return count($users);
}

/**
 * Remove push devices without a sign of life for PUSH_DEVICE_EXPIRE_DAYS (Issue #16; cron,
 * nightly). An open app refreshes its device at most daily, so this hits devices whose app is
 * gone or never used. Devices the push service reports as gone are removed right when sending.
 * Returns how many were removed.
 */
function push_devices_cleanup(PDO $pdo): int {
    set_admin_context($pdo);
    $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE updated_at < NOW() - make_interval(days => ?)");
    $stmt->execute([PUSH_DEVICE_EXPIRE_DAYS]);
    reset_rls_context($pdo);
    return $stmt->rowCount();
}
