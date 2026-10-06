-- database/migrations/20261006_resources.sql
-- Team Manager — Einmal-Migration: Ressourcen und ihre Belegung (Issue #2)
--
-- VOR dem Deployment einspielen, danach diese Datei löschen (Konvention, siehe README).
-- Legt zwei neue Tabellen an: resources (vom Admin gepflegt) und resource_bookings
-- (welche Liste bzw. welcher Termin eine Ressource nutzt), jeweils mit RLS.
-- Bestehende Daten werden nicht verändert.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Idempotent.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der Produktions-config.php

DO $$
BEGIN
    IF to_regclass('lists') IS NULL OR to_regclass('events') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen lists/events nicht gefunden)';
    END IF;
END $$;

-- Resources — bookable across all teams (pitch, hall, bus), managed by the admin
CREATE TABLE IF NOT EXISTS resources (
    id             SERIAL PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    is_active      BOOLEAN NOT NULL DEFAULT TRUE,
    calendar_token CHAR(64) NULL UNIQUE,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Resource bookings — a resource used by one list or one event; time comes from the list/event
CREATE TABLE IF NOT EXISTS resource_bookings (
    id          SERIAL PRIMARY KEY,
    resource_id INTEGER NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
    team_id     INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    list_id     INTEGER NULL REFERENCES lists(id) ON DELETE CASCADE,
    event_id    INTEGER NULL REFERENCES events(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (num_nonnulls(list_id, event_id) = 1)
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_resource_bookings_list  ON resource_bookings(resource_id, list_id)  WHERE list_id  IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_resource_bookings_event ON resource_bookings(resource_id, event_id) WHERE event_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_resource_bookings_list  ON resource_bookings(list_id);
CREATE INDEX IF NOT EXISTS idx_resource_bookings_event ON resource_bookings(event_id);

ALTER TABLE resources ENABLE ROW LEVEL SECURITY;
ALTER TABLE resources FORCE ROW LEVEL SECURITY;
ALTER TABLE resource_bookings ENABLE ROW LEVEL SECURITY;
ALTER TABLE resource_bookings FORCE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS resources_select ON resources;
CREATE POLICY resources_select ON resources FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR NULLIF(current_setting('app.current_team_id', true), '')::integer IS NOT NULL
);
DROP POLICY IF EXISTS resources_insert ON resources;
CREATE POLICY resources_insert ON resources FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
DROP POLICY IF EXISTS resources_update ON resources;
CREATE POLICY resources_update ON resources FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
DROP POLICY IF EXISTS resources_delete ON resources;
CREATE POLICY resources_delete ON resources FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);
DROP POLICY IF EXISTS resource_bookings_select ON resource_bookings;
CREATE POLICY resource_bookings_select ON resource_bookings FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
DROP POLICY IF EXISTS resource_bookings_insert ON resource_bookings;
CREATE POLICY resource_bookings_insert ON resource_bookings FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);
DROP POLICY IF EXISTS resource_bookings_delete ON resource_bookings;
CREATE POLICY resource_bookings_delete ON resource_bookings FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

COMMIT;
