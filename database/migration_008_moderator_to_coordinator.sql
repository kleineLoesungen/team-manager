-- Migration 008: rename role value moderator → coordinator
-- Run in pgAdmin connected to production database.
-- Safe to run even if already applied (idempotent).
-- Requires: SET app.is_admin = 'true' first (needed to bypass FORCE RLS for DML).

SET search_path = manager;
SET app.is_admin = 'true';

-- Update CHECK constraint
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
UPDATE users SET role = 'coordinator' WHERE role = 'moderator';
ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('coordinator', 'member'));

-- Recreate lists RLS policies
DROP POLICY IF EXISTS lists_visibility_select ON lists;
CREATE POLICY lists_visibility_select ON lists FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (visibility IN ('public', 'protected') AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

DROP POLICY IF EXISTS lists_insert ON lists;
CREATE POLICY lists_insert ON lists FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

DROP POLICY IF EXISTS lists_update ON lists;
CREATE POLICY lists_update ON lists FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

DROP POLICY IF EXISTS lists_delete ON lists;
CREATE POLICY lists_delete ON lists FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

-- Recreate columns RLS policies
DROP POLICY IF EXISTS columns_visibility_select ON columns;
CREATE POLICY columns_visibility_select ON columns FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (list_id IS NULL AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (list_id IS NOT NULL AND coach_only = FALSE AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM lists WHERE lists.id = columns.list_id AND lists.visibility IN ('public', 'protected')))
);

DROP POLICY IF EXISTS columns_insert ON columns;
CREATE POLICY columns_insert ON columns FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

DROP POLICY IF EXISTS columns_delete ON columns;
CREATE POLICY columns_delete ON columns FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

-- Recreate list_global_columns RLS policies
DROP POLICY IF EXISTS lgc_insert ON list_global_columns;
CREATE POLICY lgc_insert ON list_global_columns FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND EXISTS (SELECT 1 FROM lists WHERE lists.id = list_global_columns.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
);

DROP POLICY IF EXISTS lgc_delete ON list_global_columns;
CREATE POLICY lgc_delete ON list_global_columns FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND EXISTS (SELECT 1 FROM lists WHERE lists.id = list_global_columns.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
);

-- Recreate cells RLS policies
DROP POLICY IF EXISTS cells_visibility_select ON cells;
CREATE POLICY cells_visibility_select ON cells FOR SELECT USING (
    EXISTS (SELECT 1 FROM lists WHERE lists.id = cells.list_id AND (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
        OR (lists.visibility IN ('public', 'protected') AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    ))
);

DROP POLICY IF EXISTS cells_insert ON cells;
CREATE POLICY cells_insert ON cells FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (current_setting('app.current_role', true) = 'member'
        AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM lists WHERE lists.id = cells.list_id AND lists.visibility = 'public' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
);

DROP POLICY IF EXISTS cells_ownership_update ON cells;
CREATE POLICY cells_ownership_update ON cells FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (current_setting('app.current_role', true) = 'member'
        AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        AND EXISTS (SELECT 1 FROM lists WHERE lists.id = cells.list_id AND lists.visibility = 'public' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
);

-- Recreate free_list_rows RLS policies
DROP POLICY IF EXISTS flr_select ON free_list_rows;
CREATE POLICY flr_select ON free_list_rows FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND EXISTS (SELECT 1 FROM lists WHERE lists.id = free_list_rows.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    OR EXISTS (SELECT 1 FROM lists WHERE lists.id = free_list_rows.list_id AND lists.visibility IN ('public', 'protected') AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

DROP POLICY IF EXISTS flr_insert ON free_list_rows;
CREATE POLICY flr_insert ON free_list_rows FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND EXISTS (SELECT 1 FROM lists WHERE lists.id = free_list_rows.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
);

DROP POLICY IF EXISTS flr_delete ON free_list_rows;
CREATE POLICY flr_delete ON free_list_rows FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND EXISTS (SELECT 1 FROM lists WHERE lists.id = free_list_rows.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
);
