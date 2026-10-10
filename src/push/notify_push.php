<?php
// src/push/notify_push.php — Push-Benachrichtigung durch Koordinatoren (Issue #11)
//
// Auf der Benachrichtigungsseite einer Liste oder eines Dokuments wählt der Koordinator
// E-Mail ODER Push. Push geht an jede ausgewählte Person, und zwar an alle ihre Geräte —
// auch an Geräte, die mit dem Konto eines anderen Teams derselben Person angemeldet sind
// (users.member_id). Ein Tipp öffnet /open, das bei Bedarf ins richtige Team wechselt.
// Geräte meldet man im Profil an („Push-Benachrichtigungen auf diesem Gerät“) oder über
// „Ticker abonnieren“; auf dem iPhone nur in der installierten App.

declare(strict_types=1);

require_once ROOT_PATH . '/src/push/ticker_push.php';

const NOTIFY_PUSH_TITLE_MAX = 60;    // Sperrbildschirm zeigt nur eine Zeile Titel
const NOTIFY_PUSH_BODY_MAX  = 150;   // ungefähr das, was ohne Aufziehen sichtbar ist

/**
 * Number of push devices per user, counted over the whole person (all accounts with the same
 * member_id). [user_id => devices]; users without devices are missing.
 */
function push_device_counts(PDO $pdo, array $user_ids): array {
    $user_ids = array_values(array_unique(array_map('intval', $user_ids)));
    if (!$user_ids) return [];
    return as_admin($pdo, function () use ($pdo, $user_ids) {
        $in   = implode(',', array_fill(0, count($user_ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT r.id, COUNT(DISTINCT ps.id) AS devices
             FROM users r
             JOIN users d ON d.member_id = r.member_id AND d.is_active = TRUE
             JOIN push_subscriptions ps ON ps.user_id = d.id
             WHERE r.id IN ($in) AND r.member_id IS NOT NULL
             GROUP BY r.id"
        );
        $stmt->execute($user_ids);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'devices', 'id'));
    });
}

/** Check title and text of a push; '' when fine, else the error for the form. */
function notify_push_validate(string $title, string $body): string {
    if ($title === '')                                return 'Bitte gib einen Titel an.';
    if (mb_strlen($title) > NOTIFY_PUSH_TITLE_MAX)    return 'Titel zu lang (max. ' . NOTIFY_PUSH_TITLE_MAX . ' Zeichen).';
    if ($body === '')                                 return 'Bitte gib einen Push-Text ein.';
    if (mb_strlen($body) > NOTIFY_PUSH_BODY_MAX)      return 'Push-Text zu lang (max. ' . NOTIFY_PUSH_BODY_MAX . ' Zeichen).';
    return '';
}

/**
 * Link for the notification: /open switches to the content's team if needed, then shows the
 * list, file or event in the role of the signed-in account.
 * @param string $type 'list' | 'file' | 'event'
 */
function notify_push_url(int $team_id, string $type, int $id): string {
    return '/open?' . http_build_query(['team' => $team_id, $type => $id]);
}

/**
 * Send one notification to the devices of the given users (whole persons), after the response.
 * Removes devices the push service reports as gone.
 */
function push_send_to_users(PDO $pdo, array $user_ids, string $title, string $body, string $url): void {
    $user_ids = array_values(array_unique(array_map('intval', $user_ids)));
    if (!$user_ids) return;
    push_defer(fn() => push_send_now($pdo, $user_ids, $title, $body, $url));
}

/** Same as push_send_to_users(), right now — for code that already runs after the response. */
function push_send_now(PDO $pdo, array $user_ids, string $title, string $body, string $url): void {
    $user_ids = array_values(array_unique(array_map('intval', $user_ids)));
    if (!$user_ids) return;
    set_admin_context($pdo);
    $in   = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT ps.id, ps.endpoint, ps.p256dh, ps.auth
         FROM users r
         JOIN users d ON d.member_id = r.member_id AND d.is_active = TRUE
         JOIN push_subscriptions ps ON ps.user_id = d.id
         WHERE r.id IN ($in) AND r.member_id IS NOT NULL"
    );
    $stmt->execute($user_ids);
    $subs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subs[$row['id']] = $row;
    if ($subs) {
        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url],
                               JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $gone = [];
        foreach (webpush_send_all($subs, $payload, push_vapid($pdo), 86400, 'normal') as $id => $status) {
            if ($status === 404 || $status === 410) $gone[] = $id;
        }
        if ($gone) {
            $in = implode(',', array_fill(0, count($gone), '?'));
            $pdo->prepare("DELETE FROM push_subscriptions WHERE id IN ($in)")->execute($gone);
        }
    }
    reset_rls_context($pdo);
}

/** Active accounts of a role in a team (user ids) — recipients of automatic pushes. */
function push_team_user_ids(PDO $pdo, int $team_id, string $role): array {
    return as_admin($pdo, function () use ($pdo, $team_id, $role) {
        $stmt = $pdo->prepare(
            "SELECT u.id FROM users u JOIN teams t ON t.id = u.team_id AND t.is_active = TRUE
             WHERE u.team_id = ? AND u.role = ? AND u.is_active = TRUE"
        );
        $stmt->execute([$team_id, $role]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    });
}

/**
 * POST of the push form on a notification page: checks title, text and recipients (only people
 * with a device), sends, and redirects to $back with a success message. Returns the error and
 * the selected ids for showing the form again.
 * @param array  $with_push Recipients with at least one device (rows with 'id')
 * @param string $type      'list' | 'file'
 * @return array{0: string, 1: list<int>}
 */
function notify_push_post(PDO $pdo, array $with_push, int $team_id, string $type, int $id, string $back): array {
    $title    = trim((string)($_POST['push_title'] ?? ''));
    $body     = trim((string)($_POST['push_body'] ?? ''));
    $allowed  = array_map(fn($u) => (int)$u['id'], $with_push);
    $selected = array_values(array_intersect($allowed, array_map('intval', (array)($_POST['recipients'] ?? []))));

    $error = notify_push_validate($title, $body);
    if ($error === '' && !$selected) {
        $error = $allowed ? 'Alle Empfänger sind abgewählt. Wähle mindestens eine Person aus.'
                          : 'Niemand ist per Push erreichbar.';
    }
    if ($error !== '') return [$error, $selected];

    push_send_to_users($pdo, $selected, $title, $body, notify_push_url($team_id, $type, $id));
    $n = count($selected);
    redirect($back . (str_contains($back, '?') ? '&' : '?') . 'notify_success='
             . urlencode('Push an ' . $n . ($n === 1 ? ' Person' : ' Personen') . ' gesendet.'));
}

/**
 * Whether a notification page has anyone to reach: an active account of the role in the team
 * with an e-mail address, or whose person has a push device. Admin context (devices are RLS-only).
 */
function notify_has_recipients(PDO $pdo, int $team_id, string $role): bool {
    return as_admin($pdo, function () use ($pdo, $team_id, $role) {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM users u
             JOIN members p ON p.id = u.member_id
             WHERE u.team_id = ? AND u.role = ? AND u.is_active = TRUE
               AND (p.email IS NOT NULL OR p.contact_email IS NOT NULL
                    OR EXISTS (SELECT 1 FROM users d JOIN push_subscriptions ps ON ps.user_id = d.id
                               WHERE d.member_id = u.member_id AND d.is_active = TRUE))
             LIMIT 1"
        );
        $stmt->execute([$team_id, $role]);
        return (bool)$stmt->fetchColumn();
    });
}
