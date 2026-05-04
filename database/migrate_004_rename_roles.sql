-- Migration 004: rename user role values coach → moderator, player → mitglied
-- Idempotent: safe to run multiple times on a live database.
-- Run with: psql -U <user> -d <database> -f database/migrate_004_rename_roles.sql
--
-- What this does:
--   1. Renames role='coach' rows to role='moderator'
--   2. Renames role='player' rows to role='mitglied'
--   3. Updates the CHECK constraint on users.role
--   4. Recreates all RLS policies that reference app.current_role 'coach'/'player'

\set ON_ERROR_STOP on
SET search_path TO team_manager, public;

BEGIN;

-- ── Step 1: Data + constraint migration ──────────────────────────────────────
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM team_manager.users WHERE role IN ('coach', 'player')) THEN
        -- Remove old CHECK constraint
        ALTER TABLE team_manager.users DROP CONSTRAINT IF EXISTS users_role_check;

        -- Rename roles
        UPDATE team_manager.users SET role = 'moderator' WHERE role = 'coach';
        UPDATE team_manager.users SET role = 'mitglied'  WHERE role = 'player';

        -- Add updated CHECK constraint
        ALTER TABLE team_manager.users
            ADD CONSTRAINT users_role_check CHECK (role IN ('moderator', 'mitglied'));

        RAISE NOTICE 'Migration 004: role values renamed.';
    ELSE
        RAISE NOTICE 'Migration 004: roles already renamed, skipping data migration.';
    END IF;

    -- Always recreate CHECK in case it was left in broken state
    -- (DROP + ADD is idempotent thanks to IF EXISTS above)
END $$;

-- ── Step 2: Recreate all RLS policies referencing app.current_role ────────────

-- Lists
DROP POLICY IF EXISTS lists_visibility_select ON lists;
CREATE POLICY lists_visibility_select ON lists
    FOR SELECT
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
        OR (
            visibility IN ('public', 'protected')
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

DROP POLICY IF EXISTS lists_insert ON lists;
CREATE POLICY lists_insert ON lists
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

DROP POLICY IF EXISTS lists_update ON lists;
CREATE POLICY lists_update ON lists
    FOR UPDATE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

DROP POLICY IF EXISTS lists_delete ON lists;
CREATE POLICY lists_delete ON lists
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

-- Columns
DROP POLICY IF EXISTS columns_visibility_select ON columns;
CREATE POLICY columns_visibility_select ON columns
    FOR SELECT
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
        OR (
            list_id IS NULL
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
        OR (
            list_id IS NOT NULL
            AND coach_only = FALSE
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = columns.list_id
                AND lists.visibility IN ('public', 'protected')
            )
        )
    );

DROP POLICY IF EXISTS columns_insert ON columns;
CREATE POLICY columns_insert ON columns
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

-- list_global_columns
DROP POLICY IF EXISTS lgc_insert ON list_global_columns;
CREATE POLICY lgc_insert ON list_global_columns
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = list_global_columns.list_id
                  AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

DROP POLICY IF EXISTS lgc_delete ON list_global_columns;
CREATE POLICY lgc_delete ON list_global_columns
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = list_global_columns.list_id
                  AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

-- Cells
DROP POLICY IF EXISTS cells_visibility_select ON cells;
CREATE POLICY cells_visibility_select ON cells
    FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM lists
            WHERE lists.id = cells.list_id
            AND (
                current_setting('app.is_admin', true) = 'true'
                OR (
                    current_setting('app.current_role', true) = 'moderator'
                    AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                )
                OR (
                    lists.visibility IN ('public', 'protected')
                    AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                )
            )
        )
    );

DROP POLICY IF EXISTS cells_insert ON cells;
CREATE POLICY cells_insert ON cells
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'moderator'
        OR (
            current_setting('app.current_role', true) = 'mitglied'
            AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = cells.list_id
                AND lists.visibility = 'public'
                AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

DROP POLICY IF EXISTS cells_ownership_update ON cells;
CREATE POLICY cells_ownership_update ON cells
    FOR UPDATE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'moderator'
        OR (
            current_setting('app.current_role', true) = 'mitglied'
            AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = cells.list_id
                AND lists.visibility = 'public'
                AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

COMMIT;

\echo 'Migration 004 complete.'
