-- database/migrations/20260924_team_scoped_calendar_tokens.sql
-- Team Manager - one-time production migration
-- Run manually against the PRODUCTION schema "manager" (NOT "team_manager" - that is Docker/dev
-- only) via psql, as the schema/table OWNER, BEFORE deploying the app version that removes
-- maybe_migrate_db() and switches ICS tokens from per-user to per-team (2 tokens per team).
--
-- Sections are labeled by the privilege they need:
--   [DDL - needs table OWNER]   ALTER/DROP statements - run as the Postgres role that owns
--                               the "manager" schema (typically the role from DB_USER).
--   [DML - needs is_admin GUC] UPDATE/SELECT backfill - every table has RLS policies checking
--                               current_setting('app.is_admin', true) = 'true'; without the
--                               SET below, UPDATE silently affects 0 rows (confirmed the hard
--                               way on 2026-08-25 - see STATE.md).
--
-- Idempotent throughout - safe to re-run.
--
-- Usage: psql -h <host> -U <owner> -d <database> -f database/migrations/20260924_team_scoped_calendar_tokens.sql

SET search_path TO manager, public;

-- Required for the DML sections below to actually affect rows under RLS.
SET app.is_admin = true;

-- [DDL] New team-scoped calendar token columns
ALTER TABLE manager.teams
    ADD COLUMN IF NOT EXISTS calendar_token_coordinator VARCHAR(64) UNIQUE NULL;
ALTER TABLE manager.teams
    ADD COLUMN IF NOT EXISTS calendar_token_member      VARCHAR(64) UNIQUE NULL;

-- [DDL] Safety net - columns previously flagged as "apply manually if needed" in
-- connection.php (pre-this-migration comment). Idempotent, so including them is free;
-- production state for these two was unconfirmed before this script.
ALTER TABLE manager.teams
    ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500) NULL;
ALTER TABLE manager.users
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL;

-- [DML] Backfill both tokens for every existing team.
-- gen_random_uuid() is built into PostgreSQL 13+ - pgcrypto is NOT required.
-- Two UUIDs, hyphens stripped and concatenated, give 64 hex chars of CSPRNG output.
UPDATE manager.teams
SET calendar_token_coordinator = replace(gen_random_uuid()::text, '-', '') || replace(gen_random_uuid()::text, '-', '')
WHERE calendar_token_coordinator IS NULL;

UPDATE manager.teams
SET calendar_token_member = replace(gen_random_uuid()::text, '-', '') || replace(gen_random_uuid()::text, '-', '')
WHERE calendar_token_member IS NULL;

-- Fallback only (commented out) - if gen_random_uuid() is somehow unavailable on your PG
-- version, this needs superuser to CREATE EXTENSION; avoid unless the above fails:
-- CREATE EXTENSION IF NOT EXISTS pgcrypto;
-- UPDATE manager.teams SET calendar_token_coordinator = encode(gen_random_bytes(32), 'hex') WHERE calendar_token_coordinator IS NULL;
-- UPDATE manager.teams SET calendar_token_member      = encode(gen_random_bytes(32), 'hex') WHERE calendar_token_member      IS NULL;

-- [DDL] Drop the obsolete per-user token column - replaced by the two team-scoped columns
-- above. May not exist in production if it was never deployed there; IF EXISTS makes this
-- safe either way.
ALTER TABLE manager.users
    DROP COLUMN IF EXISTS calendar_token;

-- Verification - eyeball every team has both tokens, and the old column is gone.
SELECT id, name,
       (calendar_token_coordinator IS NOT NULL) AS has_coordinator_token,
       (calendar_token_member      IS NOT NULL) AS has_member_token
FROM manager.teams
ORDER BY id;

SELECT column_name FROM information_schema.columns
WHERE table_schema = 'manager' AND table_name = 'users' AND column_name = 'calendar_token';
-- Expect ZERO rows from the query above - confirms the old column is gone.
