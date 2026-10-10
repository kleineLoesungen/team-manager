-- database/migrations/20261010_personal_calendar.sql
-- Team Manager — Migration: persönlicher Kalender (Issue #12)
--
-- VOR dem Deployment einspielen (Reihenfolge und Anleitung: Admin → Einstellungen → Version).
-- - members.calendar_token: persönlicher Kalender-Link je Person (über alle ihre Teams),
--   wird beim ersten Aufruf von Inhalte → Kalender erzeugt.
-- - lists.calendar_column_id: Ja/Nein-Spalte, die über den Eintrag im persönlichen Kalender
--   entscheidet. Bestehende Mitgliederlisten mit GENAU EINER Ja/Nein-Spalte, die Mitglieder
--   sehen (aktiv, nicht „nur Koordinatoren“), bekommen diese Spalte gesetzt: Sie erscheinen
--   dann nur noch bei „Ja“. Alle anderen Listen bleiben ohne Spalte (erscheinen immer).
-- - teams.calendar_token_member entfällt: Der bisherige Team-Kalender der Mitglieder wird
--   abgeschaltet, alte Abos liefern danach nichts mehr. Der Koordinator-Kalender bleibt.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Mehrfach ausführbar: Datenänderungen
-- laufen nur, solange die Datenbank diese Migration noch nicht hat (settings 'db_migration').

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der Produktions-config.php
SET LOCAL app.is_admin = 'true';             -- Row-Level Security: Daten aller Teams

DO $$
BEGIN
    IF to_regclass('members') IS NULL OR to_regclass('lists') IS NULL
       OR to_regclass('columns') IS NULL OR to_regclass('teams') IS NULL
       OR to_regclass('settings') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen members/lists/columns/teams/settings nicht gefunden)';
    END IF;
END $$;

ALTER TABLE members
    ADD COLUMN IF NOT EXISTS calendar_token CHAR(64) NULL UNIQUE;

ALTER TABLE lists
    ADD COLUMN IF NOT EXISTS calendar_column_id INTEGER NULL REFERENCES columns(id) ON DELETE SET NULL;

ALTER TABLE teams
    DROP COLUMN IF EXISTS calendar_token_member;

-- Bestehende Listen: genau eine sichtbare Ja/Nein-Spalte → als Kalender-Spalte setzen.
-- Nur beim ersten Lauf: danach entscheiden die Koordinatoren (auch „Immer anzeigen“).
WITH candidates AS (
    SELECT l.id AS list_id, c.id AS column_id,
           COUNT(*) OVER (PARTITION BY l.id) AS boolean_columns
    FROM lists l
    JOIN columns c ON c.is_active = TRUE AND c.data_type = 'boolean' AND c.coach_only = FALSE
     AND (c.list_id = l.id
          OR (c.list_id IS NULL AND EXISTS (
                  SELECT 1 FROM list_global_columns lgc WHERE lgc.list_id = l.id AND lgc.column_id = c.id)))
    WHERE l.list_type = 'member' AND l.calendar_column_id IS NULL
)
UPDATE lists
SET calendar_column_id = candidates.column_id
FROM candidates
WHERE lists.id = candidates.list_id AND candidates.boolean_columns = 1
  AND NOT EXISTS (SELECT 1 FROM settings WHERE key = 'db_migration' AND value >= '20261010_personal_calendar');

-- Stand der Datenbank: diese Migration ist eingespielt
INSERT INTO settings (key, value) VALUES ('db_migration', '20261010_personal_calendar')
ON CONFLICT (key) DO UPDATE SET value = GREATEST(settings.value, EXCLUDED.value);

COMMIT;
