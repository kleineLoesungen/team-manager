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

-- [DDL] Safety net - teams.logo_path was previously flagged as "apply manually if
-- needed" in connection.php. Idempotent, so including it is free.
ALTER TABLE manager.teams
    ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500) NULL;

-- NOTE: an earlier version of this script also did
--   ALTER TABLE manager.users ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL;
-- That line was REMOVED. It failed on production with SQLSTATE 54011 ("tables can
-- have at most 1600 columns") because manager.users has exhausted its column slots -
-- dropped columns keep their pg_attribute slot forever and still count toward the cap.
-- The column is also unnecessary: the app reads email from members.email, never from
-- users.email (see src/admin/notify_coordinators_handler.php - it JOINs members p for
-- p.email). See the column-exhaustion note at the bottom of this file.

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


-- ---------------------------------------------------------------------------
-- KNOWN ISSUE: manager.users has exhausted its 1600-column limit
-- ---------------------------------------------------------------------------
-- PostgreSQL caps a table at 1600 columns, and a DROPped column keeps its
-- pg_attribute slot forever (attisdropped = true) - the slot is NEVER reused.
-- manager.users has burned through the cap, so NO new column can be added to it
-- until the table is physically rebuilt. Adding one fails with SQLSTATE 54011.
--
-- Check current usage:
--   SELECT count(*) FILTER (WHERE NOT attisdropped) AS live_cols,
--          count(*) FILTER (WHERE attisdropped)     AS dropped_cols,
--          count(*)                                  AS slots_used
--   FROM pg_attribute
--   WHERE attrelid = 'manager.users'::regclass AND attnum > 0;
--
-- For reference, the Docker/dev team_manager.users sits at 14 slots, so this is
-- purely a production artifact - almost certainly from maybe_migrate_db() running
-- ADD/DROP column cycles on every request before its body was removed (2026-08-25).
-- That code path no longer exists, so the count is stable and not still growing.
--
-- Nothing in the current app needs a new users column, so this is NOT urgent - but
-- it IS a hard blocker for any future feature that adds one. Fixing it requires
-- rebuilding the table (recreate + copy + re-apply FKs, indexes, RLS policies,
-- sequence ownership), which is delicate and should be planned separately with a
-- verified backup, not bolted onto a feature migration.
