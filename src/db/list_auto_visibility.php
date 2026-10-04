<?php
// src/db/list_auto_visibility.php — Sichtbarkeit einer Liste zeitgesteuert umstellen
//
// Pro Liste eine Regel: "auf <auto_visibility>, <auto_visibility_hours> Std. vor Beginn".
// Beginn = date + time_start (ohne Uhrzeit 00:00), deutsche Ortszeit. Typisch:
//   Anmeldeschluss — Öffentlich → Geschützt, 2 Std. vor dem Training
//   Freischalten   — Privat → Öffentlich, 48 Std. vor dem Spiel
// Ohne Cronjob: public/index.php wendet fällige Regeln vor jedem Seitenaufruf an, bevor ein
// Handler die Liste liest — wer nach der Frist hinschaut, sieht immer schon den neuen Stand.
// Jede Regel greift einmal (auto_visibility_done_at); danach darf von Hand geändert werden.
// Ändern sich Regel, Datum oder Beginn, wird sie wieder scharf (list_settings_handler.php).

declare(strict_types=1);

const LIST_AUTO_VISIBILITY_MAX_HOURS = 720;   // 30 Tage

/** Apply all due rules (all teams). Cheap when nothing is due: partial index on pending rules. */
function list_auto_visibility_apply(PDO $pdo): void {
    try {
        set_admin_context($pdo);   // betrifft Listen aller Teams; lists_update erlaubt is_admin
        $pdo->exec(
            "UPDATE lists
                SET visibility = auto_visibility, auto_visibility_done_at = NOW(), updated_at = NOW()
              WHERE auto_visibility IS NOT NULL
                AND auto_visibility_done_at IS NULL
                AND date IS NOT NULL
                AND ((date + COALESCE(time_start, TIME '00:00')) AT TIME ZONE 'Europe/Berlin')
                    - make_interval(hours => auto_visibility_hours) <= NOW()"
        );
    } catch (PDOException $e) {
        // 42703 = Spalte fehlt: Migration noch nicht eingespielt — App läuft ohne die Funktion weiter
        if ($e->getCode() !== '42703') {
            error_log('list_auto_visibility_apply: ' . $e->getMessage());
        }
    } finally {
        reset_rls_context($pdo);
    }
}

/** When the rule fires (Europe/Berlin), or null if the list has no rule or no date. */
function list_auto_visibility_due(array $list): ?DateTimeImmutable {
    if (empty($list['auto_visibility']) || empty($list['date'])) return null;
    $tz    = new DateTimeZone('Europe/Berlin');
    $start = new DateTimeImmutable($list['date'] . ' ' . substr((string)($list['time_start'] ?: '00:00'), 0, 5), $tz);
    return $start->modify('-' . (int)$list['auto_visibility_hours'] . ' hours');
}

function list_visibility_label(string $visibility): string {
    return match ($visibility) {
        'public'    => 'Öffentlich',
        'protected' => 'Geschützt',
        'private'   => 'Privat',
        default     => $visibility,
    };
}

/** "Di 07.10. um 16:00" in German local time. */
function list_auto_visibility_when(DateTimeImmutable $at): string {
    $at = $at->setTimezone(new DateTimeZone('Europe/Berlin'));
    return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)$at->format('w')] . ' ' . $at->format('d.m.') . ' um ' . $at->format('H:i');
}

/**
 * Coordinator wording: "Wird am Di 07.10. um 16:00 automatisch auf „Geschützt“ umgestellt."
 * or, once done, when it happened. null = no rule (or done and $pending_only).
 */
function list_auto_visibility_text_coordinator(array $list, bool $pending_only = false): ?string {
    if (empty($list['auto_visibility'])) return null;
    $target = list_visibility_label($list['auto_visibility']);
    if (!empty($list['auto_visibility_done_at'])) {
        return $pending_only ? null
            : 'Am ' . list_auto_visibility_when(new DateTimeImmutable($list['auto_visibility_done_at']))
              . ' automatisch auf „' . $target . '“ umgestellt.';
    }
    $due = list_auto_visibility_due($list);
    return $due
        ? 'Wird am ' . list_auto_visibility_when($due) . ' automatisch auf „' . $target . '“ umgestellt.'
        : 'Automatische Umstellung auf „' . $target . '“ wartet auf ein Datum.';
}

/**
 * Member wording — what the change means for them ("Eintragen ist bis … möglich").
 * null when there is nothing relevant to say (or done and $pending_only).
 */
function list_auto_visibility_text_member(array $list, bool $pending_only = false): ?string {
    $target  = $list['auto_visibility'] ?? '';
    $current = $list['visibility'] ?? '';
    if ($target === '' || $target === null) return null;
    if (!empty($list['auto_visibility_done_at'])) {
        return (!$pending_only && $target === 'protected' && $current === 'protected')
            ? 'Eintragen ist seit ' . list_auto_visibility_when(new DateTimeImmutable($list['auto_visibility_done_at'])) . ' geschlossen.'
            : null;
    }
    $due = list_auto_visibility_due($list);
    if (!$due) return null;
    $when = list_auto_visibility_when($due);
    return match (true) {
        $target === 'protected' && $current === 'public'    => "Eintragen ist bis $when möglich.",
        $target === 'public'    && $current === 'protected' => "Eintragen ist ab $when möglich.",
        $target === 'private'                              => "Die Liste wird am $when ausgeblendet.",
        default                                            => null,
    };
}

/** Short point in time for badges: "heute 16:00", "morgen 16:00", "Mo 16:00", later "Mo 12.10. 16:00". */
function list_auto_visibility_short_when(DateTimeImmutable $at): string {
    $tz    = new DateTimeZone('Europe/Berlin');
    $at    = $at->setTimezone($tz);
    $days  = (int)(new DateTimeImmutable('today', $tz))->diff($at->setTime(0, 0))->format('%r%a');
    $time  = $at->format('H:i');
    $wd    = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)$at->format('w')];
    return match (true) {
        $days === 0           => 'heute ' . $time,
        $days === 1           => 'morgen ' . $time,
        $days > 1 && $days < 7 => $wd . ' ' . $time,
        default               => $wd . ' ' . $at->format('d.m.') . ' ' . $time,
    };
}

/**
 * Pending automatic change as a compact badge for the overview, or null. The alarm icon
 * marks automatic visibility changes everywhere (badge and reminder rows).
 * Members: what it means for them ("bis …" / "ab …" to enter, "bis …" visible);
 * coordinators: the target visibility and when.
 * @return array{type: string, icon: string, label: string}|null
 */
function list_auto_visibility_badge(array $list, bool $is_coordinator): ?array {
    $target = $list['auto_visibility'] ?? '';
    if ($target === '' || $target === null || !empty($list['auto_visibility_done_at'])) return null;
    $due = list_auto_visibility_due($list);
    if (!$due) return null;
    $when = list_auto_visibility_short_when($due);
    if ($is_coordinator) {
        return ['type' => 'info', 'icon' => 'bi-alarm', 'label' => '→ ' . list_visibility_label($target) . ' ' . $when];
    }
    $current = $list['visibility'] ?? '';
    return match (true) {
        $target === 'protected' && $current === 'public'    => ['type' => 'warn', 'icon' => 'bi-alarm', 'label' => 'bis ' . $when],
        $target === 'public'    && $current === 'protected' => ['type' => 'info', 'icon' => 'bi-alarm', 'label' => 'ab ' . $when],
        $target === 'private'                              => ['type' => 'dim',  'icon' => 'bi-alarm', 'label' => 'bis ' . $when],
        default                                            => null,
    };
}

/**
 * Reminder row in the overview for a list dated later whose visibility changes soon:
 * headline (what happens), time and tone (warn = closes, info = opens, dim = hidden).
 * @return array{title: string, time: string, tone: string}|null
 */
function list_auto_visibility_reminder(array $list, bool $is_coordinator): ?array {
    $badge = list_auto_visibility_badge($list, $is_coordinator);
    $due   = list_auto_visibility_due($list);
    if (!$badge || !$due) return null;
    $target  = $list['auto_visibility'];
    $current = $list['visibility'] ?? '';
    $title = $is_coordinator
        ? 'Wird ' . list_visibility_label($target)
        : match (true) {
            $target === 'protected' && $current === 'public'    => 'Anmeldeschluss',
            $target === 'public'    && $current === 'protected' => 'Anmeldung öffnet',
            default                                            => 'Wird ausgeblendet',
        };
    $tone = match ($target) { 'protected' => 'warn', 'public' => 'info', default => 'dim' };
    return ['title' => $title, 'time' => $due->setTimezone(new DateTimeZone('Europe/Berlin'))->format('H:i'), 'tone' => $tone];
}

