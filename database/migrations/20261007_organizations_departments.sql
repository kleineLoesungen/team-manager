-- database/migrations/20261007_organizations_departments.sql
-- Team Manager — Einmal-Migration zu Issue #6
--
-- VOR dem Deployment einspielen, danach diese Datei löschen (Konvention, siehe README).
--
-- Teil 1: Klubs heißen jetzt Organisationen — durchgängig, nicht nur in der Oberfläche:
--   Tabelle clubs → organizations, Spalten members.club_id / users.club_id → organization_id,
--   dazu Sequenz, Primär- und Fremdschlüssel, Index und RLS-Richtlinien. Daten bleiben unverändert.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Idempotent.

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

COMMIT;
