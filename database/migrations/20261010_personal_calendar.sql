-- database/migrations/20261010_personal_calendar.sql
-- Team Manager — Einmal-Migration: persönlicher Kalender (Issue #12)
--
-- VOR dem Deployment einspielen, danach diese Datei löschen (Konvention, siehe README).
-- - members.calendar_token: persönlicher Kalender-Link je Person (über alle ihre Teams),
--   wird beim ersten Aufruf von Inhalte → Kalender erzeugt.
-- - lists.calendar_column_id: Ja/Nein-Spalte, die über den Eintrag im persönlichen Kalender
--   entscheidet. Bestehende Listen bleiben ohne Spalte und erscheinen damit wie bisher.
-- - teams.calendar_token_member entfällt: Der bisherige Team-Kalender der Mitglieder wird
--   abgeschaltet, alte Abos liefern danach nichts mehr. Der Koordinator-Kalender bleibt.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Idempotent.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der Produktions-config.php

DO $$
BEGIN
    IF to_regclass('members') IS NULL OR to_regclass('lists') IS NULL
       OR to_regclass('columns') IS NULL OR to_regclass('teams') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen members/lists/columns/teams nicht gefunden)';
    END IF;
END $$;

ALTER TABLE members
    ADD COLUMN IF NOT EXISTS calendar_token CHAR(64) NULL UNIQUE;

ALTER TABLE lists
    ADD COLUMN IF NOT EXISTS calendar_column_id INTEGER NULL REFERENCES columns(id) ON DELETE SET NULL;

ALTER TABLE teams
    DROP COLUMN IF EXISTS calendar_token_member;

COMMIT;
