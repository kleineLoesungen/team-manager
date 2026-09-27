<?php
// src/db/ticker_seen.php — "Neu" im Live-Ticker
//
// Ein roter Punkt am Reiter "Ticker" zeigt, dass es im aktuellen Team einen laufenden Ticker
// gibt, der nach dem letzten Öffnen der Ticker-Übersicht angelegt wurde. In der Übersicht
// tragen diese Ticker ein "Neu"; das Öffnen der Übersicht setzt den Zeitpunkt neu.
// So erfährt man von neuen Tickern, ohne jeden einzelnen abonnieren zu müssen.

declare(strict_types=1);

/** When the user last opened the ticker overview of this team (null = never). */
function ticker_seen_at(PDO $pdo, int $user_id, int $team_id): ?string {
    $stmt = $pdo->prepare("SELECT seen_at FROM ticker_seen WHERE user_id = ? AND team_id = ?");
    $stmt->execute([$user_id, $team_id]);
    $at = $stmt->fetchColumn();
    return $at === false ? null : (string)$at;
}

function ticker_mark_seen(PDO $pdo, int $user_id, int $team_id): void {
    $pdo->prepare(
        "INSERT INTO ticker_seen (user_id, team_id, seen_at) VALUES (?, ?, NOW())
         ON CONFLICT (user_id, team_id) DO UPDATE SET seen_at = NOW()"
    )->execute([$user_id, $team_id]);
}

/** Is there a running ticker in this team the user has not seen in the overview yet? */
function ticker_has_unseen(PDO $pdo, int $user_id, int $team_id): bool {
    $stmt = $pdo->prepare(
        "SELECT EXISTS (
            SELECT 1 FROM tickers t
            WHERE t.team_id = ? AND t.status = 'active'
              AND t.created_at > COALESCE(
                  (SELECT seen_at FROM ticker_seen WHERE user_id = ? AND team_id = ?), '-infinity')
         )"
    );
    $stmt->execute([$team_id, $user_id, $team_id]);
    return (bool)$stmt->fetchColumn();
}

/** "Neu" badge for one ticker row of the overview. */
function ticker_is_new(array $ticker, ?string $seen_before): bool {
    return $ticker['status'] === 'active'
        && ($seen_before === null || strtotime($ticker['created_at']) > strtotime($seen_before));
}
