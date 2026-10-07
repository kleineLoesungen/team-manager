-- database/migrations/20261007_organizations_departments.sql
-- Team Manager — Einmal-Migration zu Issue #6
--
-- VOR dem Deployment einspielen, danach diese Datei löschen (Konvention, siehe README).
--
-- Teil 1: Klubs heißen jetzt Organisationen — durchgängig, nicht nur in der Oberfläche:
--   Tabelle clubs → organizations, Spalten members.club_id / users.club_id → organization_id,
--   dazu Sequenz, Primär- und Fremdschlüssel, Index und RLS-Richtlinien. Daten bleiben unverändert.
--
-- Teil 2: Abteilungen (neue Tabelle departments). Jedes Team und jede Ressource gehört zu
--   genau einer Abteilung. Bestehende Teams und Ressourcen kommen in die Abteilung
--   „Allgemein"; umbenennen oder verteilen danach im Admin unter Einstellungen → Abteilungen.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Idempotent. Läuft die App mit einem
-- anderen DB-Benutzer als dem Eigentümer, braucht er Rechte auf die neue Tabelle departments
-- (database/grants.sql, -v app_user=…).

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der Produktions-config.php

DO $$
BEGIN
    IF to_regclass('teams') IS NULL OR to_regclass('members') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen teams/members nicht gefunden)';
    END IF;
END $$;

-- ── Teil 1: Klubs → Organisationen ─────────────────────────────────────────

DO $$
BEGIN
    IF to_regclass('clubs') IS NOT NULL AND to_regclass('organizations') IS NULL THEN
        ALTER TABLE clubs RENAME TO organizations;
    END IF;
    IF to_regclass('clubs_id_seq') IS NOT NULL THEN
        ALTER SEQUENCE clubs_id_seq RENAME TO organizations_id_seq;
    END IF;
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'clubs_pkey' AND conrelid = 'organizations'::regclass) THEN
        ALTER TABLE organizations RENAME CONSTRAINT clubs_pkey TO organizations_pkey;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'members' AND column_name = 'club_id') THEN
        ALTER TABLE members RENAME COLUMN club_id TO organization_id;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'users' AND column_name = 'club_id') THEN
        ALTER TABLE users RENAME COLUMN club_id TO organization_id;
    END IF;
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'members_club_id_fkey' AND conrelid = 'members'::regclass) THEN
        ALTER TABLE members RENAME CONSTRAINT members_club_id_fkey TO members_organization_id_fkey;
    END IF;
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_club_id_fkey' AND conrelid = 'users'::regclass) THEN
        ALTER TABLE users RENAME CONSTRAINT users_club_id_fkey TO users_organization_id_fkey;
    END IF;
    IF to_regclass('idx_members_club') IS NOT NULL THEN
        ALTER INDEX idx_members_club RENAME TO idx_members_organization;
    END IF;
END $$;

DROP POLICY IF EXISTS clubs_select ON organizations;
DROP POLICY IF EXISTS clubs_insert ON organizations;
DROP POLICY IF EXISTS clubs_update ON organizations;
DROP POLICY IF EXISTS clubs_delete ON organizations;
DROP POLICY IF EXISTS organizations_select ON organizations;
DROP POLICY IF EXISTS organizations_insert ON organizations;
DROP POLICY IF EXISTS organizations_update ON organizations;
DROP POLICY IF EXISTS organizations_delete ON organizations;
CREATE POLICY organizations_select ON organizations FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
);
CREATE POLICY organizations_insert ON organizations FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY organizations_update ON organizations FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY organizations_delete ON organizations FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);

-- ── Teil 2: Abteilungen ────────────────────────────────────────────────────

SELECT set_config('app.is_admin', 'true', true);   -- RLS: resources darf nur der Admin-Kontext ändern

CREATE TABLE IF NOT EXISTS departments (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
INSERT INTO departments (name)
SELECT 'Allgemein' WHERE NOT EXISTS (SELECT 1 FROM departments);

ALTER TABLE teams     ADD COLUMN IF NOT EXISTS department_id INTEGER NULL REFERENCES departments(id);
ALTER TABLE resources ADD COLUMN IF NOT EXISTS department_id INTEGER NULL REFERENCES departments(id);
UPDATE teams     SET department_id = (SELECT MIN(id) FROM departments) WHERE department_id IS NULL;
UPDATE resources SET department_id = (SELECT MIN(id) FROM departments) WHERE department_id IS NULL;
ALTER TABLE teams     ALTER COLUMN department_id SET NOT NULL;
ALTER TABLE resources ALTER COLUMN department_id SET NOT NULL;
CREATE INDEX IF NOT EXISTS idx_teams_department     ON teams(department_id);
CREATE INDEX IF NOT EXISTS idx_resources_department ON resources(department_id);

ALTER TABLE departments ENABLE ROW LEVEL SECURITY;
ALTER TABLE departments FORCE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS departments_select ON departments;
DROP POLICY IF EXISTS departments_insert ON departments;
DROP POLICY IF EXISTS departments_update ON departments;
DROP POLICY IF EXISTS departments_delete ON departments;
CREATE POLICY departments_select ON departments FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
);
CREATE POLICY departments_insert ON departments FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY departments_update ON departments FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY departments_delete ON departments FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);

COMMIT;
