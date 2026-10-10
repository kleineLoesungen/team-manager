-- database/migrations/20261010_push_reminders.sql
-- Team Manager — Migration: Push-Erinnerung vor der automatischen Umstellung (Issue #13)
--
-- VOR dem Deployment einspielen (Reihenfolge und Anleitung: Admin → Einstellungen → Version).
-- - lists.auto_reminder_sent_at: wann die Push-Erinnerung vor der automatischen Umstellung
--   der Sichtbarkeit (z. B. Anmeldeschluss) verschickt wurde — genau einmal, am Tag der
--   Umstellung beim ersten Seitenaufruf davor. Gilt für alle Listen mit Umstellung.
-- Daten werden nicht verändert.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Mehrfach ausführbar.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der config.php der Instanz
SET LOCAL app.is_admin = 'true';             -- Row-Level Security (settings)

DO $$
BEGIN
    IF to_regclass('lists') IS NULL OR to_regclass('settings') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen lists/settings nicht gefunden)';
    END IF;
END $$;

ALTER TABLE lists
    ADD COLUMN IF NOT EXISTS auto_reminder_sent_at TIMESTAMPTZ NULL;

-- Stand der Datenbank: diese Migration ist eingespielt
INSERT INTO settings (key, value) VALUES ('db_migration', '20261010_push_reminders')
ON CONFLICT (key) DO UPDATE SET value = GREATEST(settings.value, EXCLUDED.value);

COMMIT;
