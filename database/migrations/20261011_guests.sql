-- database/migrations/20261011_guests.sql
-- Team Manager — Migration: Gastbereich ohne Anmeldung (Issue #15)
--
-- VOR dem Deployment einspielen (Reihenfolge und Anleitung: Admin → Einstellungen → Version).
-- - lists.guest_visible, events.guest_visible: „Für Gäste sichtbar“ (nur wirksam, solange nicht
--   privat). Standard: aus — bestehende Einträge bleiben für Gäste unsichtbar.
-- - resources.guest_visible: Belegung im Gastbereich. Standard: aus.
-- - teams.calendar_token_guest: Gast-Kalender je Team, wird beim ersten Aufruf erzeugt.
-- - push_subscriptions: user_id darf leer sein (Gast-Gerät), neu device_token (Cookie, erkennt das
--   Gerät auch ohne Anmeldung).
-- - ticker_subscriptions: Abo je Ticker und GERÄT statt je Benutzer. Bestehende Abos werden auf
--   alle Geräte des jeweiligen Kontos übertragen; es geht kein Abo verloren.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Mehrfach ausführbar.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der config.php der Instanz
SET LOCAL app.is_admin = 'true';             -- Row-Level Security: Daten aller Teams

DO $$
BEGIN
    IF to_regclass('lists') IS NULL OR to_regclass('events') IS NULL OR to_regclass('resources') IS NULL
       OR to_regclass('teams') IS NULL OR to_regclass('push_subscriptions') IS NULL
       OR to_regclass('ticker_subscriptions') IS NULL OR to_regclass('settings') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen lists/events/resources/teams/push_subscriptions/ticker_subscriptions/settings nicht gefunden)';
    END IF;
END $$;

ALTER TABLE lists     ADD COLUMN IF NOT EXISTS guest_visible BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE events    ADD COLUMN IF NOT EXISTS guest_visible BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE resources ADD COLUMN IF NOT EXISTS guest_visible BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE teams     ADD COLUMN IF NOT EXISTS calendar_token_guest VARCHAR(64) NULL UNIQUE;

ALTER TABLE push_subscriptions ALTER COLUMN user_id DROP NOT NULL;
ALTER TABLE push_subscriptions ADD COLUMN IF NOT EXISTS device_token CHAR(64) NULL UNIQUE;

-- Ticker-Abos: Benutzer → Gerät (nur solange die alte Spalte user_id noch da ist)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'ticker_subscriptions'
                 AND column_name = 'user_id') THEN
        ALTER TABLE ticker_subscriptions DROP CONSTRAINT IF EXISTS ticker_subscriptions_pkey;
        ALTER TABLE ticker_subscriptions
            ADD COLUMN IF NOT EXISTS subscription_id INTEGER NULL REFERENCES push_subscriptions(id) ON DELETE CASCADE;
        -- jedes bestehende Abo auf alle Geräte dieses Kontos übertragen, dann die alten Zeilen weg
        INSERT INTO ticker_subscriptions (ticker_id, user_id, subscription_id, created_at)
        SELECT ts.ticker_id, ts.user_id, ps.id, ts.created_at
        FROM ticker_subscriptions ts
        JOIN push_subscriptions ps ON ps.user_id = ts.user_id
        WHERE ts.subscription_id IS NULL;
        DELETE FROM ticker_subscriptions WHERE subscription_id IS NULL;
        DROP INDEX IF EXISTS idx_ticker_subscriptions_user;
        DROP POLICY IF EXISTS ticker_subscriptions_all ON ticker_subscriptions;
        ALTER TABLE ticker_subscriptions DROP COLUMN user_id;
        ALTER TABLE ticker_subscriptions ALTER COLUMN subscription_id SET NOT NULL;
        ALTER TABLE ticker_subscriptions ADD PRIMARY KEY (ticker_id, subscription_id);
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_ticker_subscriptions_device ON ticker_subscriptions(subscription_id);

-- Abos hängen am Gerät (auch Gäste ohne Kontext): Zugriff nur im Admin-Kontext
DROP POLICY IF EXISTS ticker_subscriptions_all ON ticker_subscriptions;
CREATE POLICY ticker_subscriptions_all ON ticker_subscriptions FOR ALL USING (
    current_setting('app.is_admin', true) = 'true'
) WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);

-- Stand der Datenbank: diese Migration ist eingespielt
INSERT INTO settings (key, value) VALUES ('db_migration', '20261011_guests')
ON CONFLICT (key) DO UPDATE SET value = GREATEST(settings.value, EXCLUDED.value);

COMMIT;
