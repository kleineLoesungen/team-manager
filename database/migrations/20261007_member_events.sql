-- database/migrations/20261007_member_events.sql
-- Team Manager — Einmal-Migration: Mitglieder dürfen Termine anlegen (Issue #4)
--
-- VOR dem Deployment einspielen, danach diese Datei löschen (Konvention, siehe README).
-- Neue Spalten teams.members_create_events (Schalter je Team, Standard aus) und
-- events.created_by (wer den Termin angelegt hat; bestehende Termine bleiben NULL).
-- RLS: Mitglieder legen bei freigeschaltetem Team Termine an und ändern/löschen nur ihre
-- eigenen, samt deren Ressourcen-Belegung. Für Koordinatoren ändert sich nichts.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Idempotent.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der Produktions-config.php

DO $$
BEGIN
    IF to_regclass('events') IS NULL OR to_regclass('resource_bookings') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen events/resource_bookings nicht gefunden)';
    END IF;
END $$;

ALTER TABLE teams  ADD COLUMN IF NOT EXISTS members_create_events BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE events ADD COLUMN IF NOT EXISTS created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL;

DROP POLICY IF EXISTS events_insert ON events;
CREATE POLICY events_insert ON events FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (current_setting('app.current_role', true) = 'member'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        AND created_by = NULLIF(current_setting('app.current_user_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM teams t WHERE t.id = events.team_id AND t.members_create_events)
        AND visibility = 'protected')
);

DROP POLICY IF EXISTS events_update ON events;
CREATE POLICY events_update ON events FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (current_setting('app.current_role', true) = 'member'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        AND created_by = NULLIF(current_setting('app.current_user_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM teams t WHERE t.id = events.team_id AND t.members_create_events))
) WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (current_setting('app.current_role', true) = 'member'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        AND created_by = NULLIF(current_setting('app.current_user_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM teams t WHERE t.id = events.team_id AND t.members_create_events)
        AND visibility = 'protected')
);

DROP POLICY IF EXISTS events_delete ON events;
CREATE POLICY events_delete ON events FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (current_setting('app.current_role', true) = 'member'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        AND created_by = NULLIF(current_setting('app.current_user_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM teams t WHERE t.id = events.team_id AND t.members_create_events))
);

DROP POLICY IF EXISTS resource_bookings_insert ON resource_bookings;
CREATE POLICY resource_bookings_insert ON resource_bookings FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (current_setting('app.current_role', true) = 'member'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM events e
                    WHERE e.id = resource_bookings.event_id AND e.team_id = resource_bookings.team_id
                      AND e.created_by = NULLIF(current_setting('app.current_user_id', true), '')::integer))
);

DROP POLICY IF EXISTS resource_bookings_delete ON resource_bookings;
CREATE POLICY resource_bookings_delete ON resource_bookings FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (current_setting('app.current_role', true) = 'member'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM events e
                    WHERE e.id = resource_bookings.event_id AND e.team_id = resource_bookings.team_id
                      AND e.created_by = NULLIF(current_setting('app.current_user_id', true), '')::integer))
);

COMMIT;
