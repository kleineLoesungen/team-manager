-- Migration 005: rename role value 'mitglied' → 'member'
-- Run AFTER migrate_004 has already been applied.
-- Idempotent: safe to run multiple times.
--
-- ⚠️ Change 'team_manager' below to your actual schema name if different (e.g., 'manager').

SET search_path TO team_manager, public;

BEGIN;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM users WHERE role = 'mitglied') THEN
        ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
        UPDATE users SET role = 'member' WHERE role = 'mitglied';
        ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('moderator', 'member'));
        RAISE NOTICE 'Migration 005: mitglied renamed to member.';
    ELSE
        RAISE NOTICE 'Migration 005: already done or not needed, skipping data migration.';
    END IF;
    -- Ensure constraint is correct regardless
    ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
    ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('moderator', 'member'));
END $$;

-- Recreate RLS policies that reference 'mitglied' → 'member'

DROP POLICY IF EXISTS cells_insert ON cells;
CREATE POLICY cells_insert ON cells
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'moderator'
        OR (
            current_setting('app.current_role', true) = 'member'
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
            current_setting('app.current_role', true) = 'member'
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
