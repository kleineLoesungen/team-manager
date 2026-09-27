-- database/migrations/20260927_ticker_viewers.sql
-- Team Manager — Einmal-Migration: Zuschauerzählung im Live-Ticker
--
-- VOR dem Deployment der App-Version mit Zuschauerzählung einspielen, danach diese
-- Datei löschen (Konvention: keine Migrationsdateien im Repo, siehe README).
--
-- >>> Schema in der Zeile `SET search_path` eintragen. <<<
--     Maßgeblich ist DB_SCHEMA aus der config.php der Umgebung. Prüfen mit:
--         SELECT table_schema FROM information_schema.tables WHERE table_name = 'tickers';
--     Steht dort ein falsches oder gar kein Schema, bricht das Skript ab, bevor es
--     etwas ändert (search_path ohne `public`, Prüfung per to_regclass).
--
-- Rechte: nur DDL (CREATE/POLICY) — als Eigentümer-Rolle der Tabellen ausführen.
-- Keine Datenänderungen, daher kein `SET app.is_admin` nötig.
-- Idempotent, läuft in einer Transaktion.
--
-- Aufruf: psql -h <host> -U <owner> -d <database> -f 20260927_ticker_viewers.sql

\set ON_ERROR_STOP on
BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< z. B. team_manager

DO $$
BEGIN
    IF to_regclass('tickers') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabelle tickers nicht gefunden)';
    END IF;
END $$;

-- Öffentliche Ticker-Seiten und der Ping schlagen den Ticker im Admin-Kontext nach, bevor
-- das Team bekannt ist. db_init_rls() legt tickers_select mit dieser Ausnahme an,
-- rls_policies.sql tat es bisher nicht — hier auf den Stand von db_init_rls() bringen.
DROP POLICY IF EXISTS tickers_select ON tickers;
CREATE POLICY tickers_select ON tickers FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);

-- Aktuelle Zuschauer: zufällige Tab-ID + letzter Kontakt, Einträge leben nur Minuten
CREATE TABLE IF NOT EXISTS ticker_viewers (
    ticker_id INTEGER     NOT NULL REFERENCES tickers(id) ON DELETE CASCADE,
    viewer_id UUID        NOT NULL,
    last_seen TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (ticker_id, viewer_id)
);
CREATE INDEX IF NOT EXISTS idx_ticker_viewers_seen ON ticker_viewers(ticker_id, last_seen);

ALTER TABLE ticker_viewers ENABLE ROW LEVEL SECURITY;
ALTER TABLE ticker_viewers FORCE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS ticker_viewers_all ON ticker_viewers;
CREATE POLICY ticker_viewers_all ON ticker_viewers FOR ALL USING (
    current_setting('app.is_admin', true) = 'true'
    OR EXISTS (
        SELECT 1 FROM tickers
        WHERE tickers.id = ticker_viewers.ticker_id
          AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )
) WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR EXISTS (
        SELECT 1 FROM tickers
        WHERE tickers.id = ticker_viewers.ticker_id
          AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )
);

-- Höchste gleichzeitige Zuschauerzahl; bleibt nach dem Schließen, fällt mit dem Ticker weg.
-- Eigene Tabelle statt Spalte in tickers: tickers_update erlaubt nur Koordinatoren, der
-- Ping kommt aber auch von anonymen Zuschauern.
CREATE TABLE IF NOT EXISTS ticker_viewer_peaks (
    ticker_id INTEGER PRIMARY KEY REFERENCES tickers(id) ON DELETE CASCADE,
    peak      INTEGER NOT NULL DEFAULT 0
);

ALTER TABLE ticker_viewer_peaks ENABLE ROW LEVEL SECURITY;
ALTER TABLE ticker_viewer_peaks FORCE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS ticker_viewer_peaks_all ON ticker_viewer_peaks;
CREATE POLICY ticker_viewer_peaks_all ON ticker_viewer_peaks FOR ALL USING (
    current_setting('app.is_admin', true) = 'true'
    OR EXISTS (
        SELECT 1 FROM tickers
        WHERE tickers.id = ticker_viewer_peaks.ticker_id
          AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )
) WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR EXISTS (
        SELECT 1 FROM tickers
        WHERE tickers.id = ticker_viewer_peaks.ticker_id
          AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )
);

COMMIT;
