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
