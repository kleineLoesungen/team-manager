<?php
// src/db/ticker_viewers.php — Zuschauerzählung im Live-Ticker
//
// Jede geöffnete, sichtbare Ticker-Seite meldet sich alle 30 s per POST /ticker/{id}/ping.
// Ein Zuschauer ist eine Browser-Sitzung: Die ID ist ein Hash aus der ohnehin bestehenden
// Sitzungs-ID und der Ticker-ID — kein zusätzliches Cookie, kein localStorage, keine IP.
// Mehrfaches Öffnen und mehrere Tabs zählen so einmal. "Aktiv" = in den letzten 60 s gemeldet.
// Beim Schließen des Tickers werden die Einzeleinträge gelöscht; nur ticker_viewer_peaks
// bleibt, bis der Ticker gelöscht wird.

declare(strict_types=1);

const TICKER_VIEWER_ACTIVE_SECONDS = 60;
const TICKER_VIEWER_PING_SECONDS   = 30;   // muss zum Intervall im Layout-Skript passen

/**
 * Record a heartbeat, drop entries that can no longer count as active, raise the peak.
 * Needs the ticker's team context (RLS on ticker_viewers / ticker_viewer_peaks).
 * @return array{active: int, max: int}
 */
function ticker_viewers_record(PDO $pdo, int $ticker_id, string $viewer_id): array {
    $pdo->prepare(
        "INSERT INTO ticker_viewers (ticker_id, viewer_id, last_seen) VALUES (?, ?, NOW())
         ON CONFLICT (ticker_id, viewer_id) DO UPDATE SET last_seen = NOW()"
    )->execute([$ticker_id, $viewer_id]);

    $pdo->prepare(
        "DELETE FROM ticker_viewers
         WHERE ticker_id = ? AND last_seen < NOW() - make_interval(secs => ?)"
    )->execute([$ticker_id, 2 * TICKER_VIEWER_ACTIVE_SECONDS]);

    $counts = ticker_viewers_counts($pdo, $ticker_id);
    if ($counts['active'] > $counts['max']) {
        $stmt = $pdo->prepare(
            "INSERT INTO ticker_viewer_peaks (ticker_id, peak) VALUES (?, ?)
             ON CONFLICT (ticker_id) DO UPDATE SET peak = GREATEST(ticker_viewer_peaks.peak, EXCLUDED.peak)
             RETURNING peak"
        );
        $stmt->execute([$ticker_id, $counts['active']]);
        $counts['max'] = (int)$stmt->fetchColumn();
    }
    return $counts;
}

/**
 * Current and maximum viewer count of a ticker.
 * @return array{active: int, max: int}
 */
function ticker_viewers_counts(PDO $pdo, int $ticker_id): array {
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM ticker_viewers
             WHERE ticker_id = ? AND last_seen > NOW() - make_interval(secs => ?)) AS active,
            COALESCE((SELECT peak FROM ticker_viewer_peaks WHERE ticker_id = ?), 0) AS max"
    );
    $stmt->execute([$ticker_id, TICKER_VIEWER_ACTIVE_SECONDS, $ticker_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['active' => (int)$row['active'], 'max' => (int)$row['max']];
}

/**
 * Forget individual viewers once a ticker is closed; only the peak stays.
 */
function ticker_viewers_clear(PDO $pdo, int $ticker_id): void {
    $pdo->prepare("DELETE FROM ticker_viewers WHERE ticker_id = ?")->execute([$ticker_id]);
}
