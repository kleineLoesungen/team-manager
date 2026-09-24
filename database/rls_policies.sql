-- PostgreSQL Row-Level Security Policies
-- Apply AFTER schema.sql has been run.
-- Defense-in-depth: application layer ALSO enforces team_id checks.
--
-- Two bypass mechanisms:
--   app.current_team_id — set per request for coach/player sessions (team isolation)
--   app.is_admin        — set per request for admin sessions (cross-team access)

SET search_path TO team_manager, public;

ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE users FORCE ROW LEVEL SECURITY;

-- SELECT: admin sees all rows; others see only their own team
-- A user must always be able to read their own row: users.team_id is the ORIGIN team,
-- while app.current_team_id is the team being worked in. For a coordinator active in any
-- other team the two differ, which otherwise hides their own row — blanking the profile
-- page and, via members_select's subquery on users, their own member record too.
CREATE POLICY team_isolation_users_select ON users
    FOR SELECT
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        OR id      = NULLIF(current_setting('app.current_user_id', true), '')::integer
    );

-- INSERT: admin can insert into any team; others only into current team context
CREATE POLICY team_isolation_users_insert ON users
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    );

-- UPDATE: admin can update any row; others only rows within their own team
CREATE POLICY team_isolation_users_update ON users
    FOR UPDATE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    );

-- DELETE: only admin can delete users (coordinator hard-delete requires deactivated + is_admin guard)
CREATE POLICY team_isolation_users_delete ON users
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
    );

-- Teams table: no RLS needed (admin manages all teams; coaches read their own via team_id FK)

-- ── Phase 3: Lists, Columns & Cells — Visibility RLS ────────────────────────
-- Note: app.current_role and app.current_user_id are set by set_team_context() in src/db/connection.php.
-- require_coordinator() passes role='coordinator'; require_player() passes role='member'.

ALTER TABLE lists   ENABLE ROW LEVEL SECURITY;
ALTER TABLE lists   FORCE ROW LEVEL SECURITY;
ALTER TABLE columns ENABLE ROW LEVEL SECURITY;
ALTER TABLE columns FORCE ROW LEVEL SECURITY;
ALTER TABLE cells   ENABLE ROW LEVEL SECURITY;
ALTER TABLE cells   FORCE ROW LEVEL SECURITY;

-- Lists SELECT: admin sees all; coaches see all lists in their team; players see public + protected lists
CREATE POLICY lists_visibility_select ON lists
    FOR SELECT
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
        OR (
            visibility IN ('public', 'protected')
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

-- Lists INSERT: admin or coach can create lists in their team
CREATE POLICY lists_insert ON lists
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

-- Lists UPDATE: admin or coach can update lists in their team
CREATE POLICY lists_update ON lists
    FOR UPDATE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

CREATE POLICY lists_delete ON lists
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

-- Columns SELECT: admin sees all; coaches see all columns in their team; players see columns for public + protected lists
CREATE POLICY columns_visibility_select ON columns
    FOR SELECT
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
        OR (
            -- Players see global columns (list_id IS NULL) for their team
            list_id IS NULL
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
        OR (
            -- Players see local columns for public or protected lists in their team
            -- coach_only columns are excluded from player visibility
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

-- Columns INSERT: only admin or coach can create columns
CREATE POLICY columns_insert ON columns
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

-- Columns DELETE: only admin or coordinator can delete columns in their team
CREATE POLICY columns_delete ON columns
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

-- Columns UPDATE: admin can update any column; coordinators can only update their own non-system columns
CREATE POLICY columns_update ON columns
    FOR UPDATE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            AND (is_system IS NULL OR is_system = FALSE)
        )
    )
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            AND (is_system IS NULL OR is_system = FALSE)
        )
    );

-- List–global-column junction: coaches manage; players read (visibility follows parent list)
ALTER TABLE list_global_columns ENABLE ROW LEVEL SECURITY;
ALTER TABLE list_global_columns FORCE ROW LEVEL SECURITY;

CREATE POLICY lgc_select ON list_global_columns
    FOR SELECT
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR EXISTS (
            SELECT 1 FROM lists
            WHERE lists.id = list_global_columns.list_id
              AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

CREATE POLICY lgc_insert ON list_global_columns
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = list_global_columns.list_id
                  AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

CREATE POLICY lgc_delete ON list_global_columns
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = list_global_columns.list_id
                  AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

CREATE POLICY lgc_update ON list_global_columns
    FOR UPDATE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = list_global_columns.list_id
                  AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    )
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = list_global_columns.list_id
                  AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

-- Cells SELECT: visibility inherited from parent list; players can read cells from public + protected lists
CREATE POLICY cells_visibility_select ON cells
    FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM lists
            WHERE lists.id = cells.list_id
            AND (
                current_setting('app.is_admin', true) = 'true'
                OR (
                    current_setting('app.current_role', true) = 'coordinator'
                    AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                )
                OR (
                    lists.visibility IN ('public', 'protected')
                    AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                )
            )
        )
    );

-- Cells INSERT: admin and coordinator can insert any cell; member can only insert their own cell in public lists
CREATE POLICY cells_insert ON cells
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND member_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = cells.list_id
                AND lists.visibility = 'public'
                AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

-- Cells DELETE: admin (bypasses RLS) or coordinator can delete cells in their team's lists
CREATE POLICY cells_delete ON cells
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = cells.list_id
                  AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

-- Cells UPDATE: admin and coordinator can update any cell; member can only update their own cell in public lists
CREATE POLICY cells_ownership_update ON cells
    FOR UPDATE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND member_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (
                SELECT 1 FROM lists
                WHERE lists.id = cells.list_id
                AND lists.visibility = 'public'
                AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )
        )
    );

-- ── Phase 7: Live-Ticker RLS ─────────────────────────────────────────────────

-- ticker_tags: coordinator can manage; anyone with team context can SELECT (for post forms)
ALTER TABLE team_manager.ticker_tags ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.ticker_tags FORCE ROW LEVEL SECURITY;

CREATE POLICY ticker_tags_select ON team_manager.ticker_tags FOR SELECT USING (
    team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
CREATE POLICY ticker_tags_insert ON team_manager.ticker_tags FOR INSERT WITH CHECK (
    current_setting('app.current_role', true) = 'coordinator'
    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
CREATE POLICY ticker_tags_update ON team_manager.ticker_tags FOR UPDATE USING (
    current_setting('app.current_role', true) = 'coordinator'
    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
CREATE POLICY ticker_tags_delete ON team_manager.ticker_tags FOR DELETE USING (
    current_setting('app.current_role', true) = 'coordinator'
    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);

-- tickers: coordinator can manage; anyone with team context can SELECT (public endpoint)
ALTER TABLE team_manager.tickers ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.tickers FORCE ROW LEVEL SECURITY;

CREATE POLICY tickers_select ON team_manager.tickers FOR SELECT USING (
    team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
CREATE POLICY tickers_insert ON team_manager.tickers FOR INSERT WITH CHECK (
    current_setting('app.current_role', true) = 'coordinator'
    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
CREATE POLICY tickers_update ON team_manager.tickers FOR UPDATE USING (
    current_setting('app.current_role', true) = 'coordinator'
    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
CREATE POLICY tickers_delete ON team_manager.tickers FOR DELETE USING (
    current_setting('app.current_role', true) = 'coordinator'
    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);

-- ticker_messages: anyone with team context can SELECT; coordinator + member can write (PHP enforces freigabe)
ALTER TABLE team_manager.ticker_messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.ticker_messages FORCE ROW LEVEL SECURITY;

CREATE POLICY ticker_messages_select ON team_manager.ticker_messages FOR SELECT USING (
    EXISTS (
        SELECT 1 FROM team_manager.tickers
        WHERE tickers.id = ticker_messages.ticker_id
          AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )
);
CREATE POLICY ticker_messages_insert ON team_manager.ticker_messages FOR INSERT WITH CHECK (
    current_setting('app.current_role', true) IN ('coordinator', 'member')
    AND EXISTS (
        SELECT 1 FROM team_manager.tickers
        WHERE tickers.id = ticker_messages.ticker_id
          AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )
);
CREATE POLICY ticker_messages_update ON team_manager.ticker_messages FOR UPDATE USING (
    current_setting('app.current_role', true) IN ('coordinator', 'member')
    AND EXISTS (
        SELECT 1 FROM team_manager.tickers
        WHERE tickers.id = ticker_messages.ticker_id
          AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )
);
CREATE POLICY ticker_messages_delete ON team_manager.ticker_messages FOR DELETE USING (
    current_setting('app.current_role', true) IN ('coordinator', 'member')
    AND EXISTS (
        SELECT 1 FROM team_manager.tickers
        WHERE tickers.id = ticker_messages.ticker_id
          AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )
);

-- ticker_members: coordinator can manage; coordinator + freigegeben member can SELECT
ALTER TABLE team_manager.ticker_members ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.ticker_members FORCE ROW LEVEL SECURITY;

CREATE POLICY ticker_members_select ON team_manager.ticker_members FOR SELECT USING (
    team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    AND current_setting('app.current_role', true) IN ('coordinator', 'member')
);
CREATE POLICY ticker_members_insert ON team_manager.ticker_members FOR INSERT WITH CHECK (
    current_setting('app.current_role', true) = 'coordinator'
    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
CREATE POLICY ticker_members_delete ON team_manager.ticker_members FOR DELETE USING (
    current_setting('app.current_role', true) = 'coordinator'
    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);

-- ── Phase 8: Member & Club Management RLS ─────────────────────────────────────

ALTER TABLE team_manager.clubs ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.clubs FORCE ROW LEVEL SECURITY;

CREATE POLICY clubs_select ON team_manager.clubs FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
);
CREATE POLICY clubs_insert ON team_manager.clubs FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY clubs_update ON team_manager.clubs FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY clubs_delete ON team_manager.clubs FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);

ALTER TABLE team_manager.member_attribute_groups ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.member_attribute_groups FORCE ROW LEVEL SECURITY;

CREATE POLICY mag_select ON team_manager.member_attribute_groups FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
);
CREATE POLICY mag_insert ON team_manager.member_attribute_groups FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY mag_update ON team_manager.member_attribute_groups FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY mag_delete ON team_manager.member_attribute_groups FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);

ALTER TABLE team_manager.members ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.members FORCE ROW LEVEL SECURITY;

CREATE POLICY members_select ON team_manager.members FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR (
        current_setting('app.current_role', true) IN ('coordinator', 'member')
        AND EXISTS (
            SELECT 1 FROM team_manager.users u
            WHERE u.member_id = members.id
              AND u.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
              AND u.role = 'member'
        )
    )
    OR EXISTS (
        SELECT 1 FROM team_manager.users u
        WHERE u.member_id = members.id
          AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
    )
);
CREATE POLICY members_insert ON team_manager.members FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY members_update ON team_manager.members FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY members_delete ON team_manager.members FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);

ALTER TABLE team_manager.coordinator_teams ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.coordinator_teams FORCE ROW LEVEL SECURITY;

CREATE POLICY ct_select ON team_manager.coordinator_teams FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
);
CREATE POLICY ct_insert ON team_manager.coordinator_teams FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY ct_update ON team_manager.coordinator_teams FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);

ALTER TABLE team_manager.member_attributes ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.member_attributes FORCE ROW LEVEL SECURITY;

CREATE POLICY ma_select ON team_manager.member_attributes FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (
        current_setting('app.current_role', true) = 'member'
        AND visible_to_player = TRUE
    )
);
CREATE POLICY ma_insert ON team_manager.member_attributes FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY ma_update ON team_manager.member_attributes FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY ma_delete ON team_manager.member_attributes FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);

ALTER TABLE team_manager.member_attribute_values ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.member_attribute_values FORCE ROW LEVEL SECURITY;

CREATE POLICY mav_select ON team_manager.member_attribute_values FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (
        current_setting('app.current_role', true) = 'member'
        AND EXISTS (
            SELECT 1 FROM team_manager.users u
            WHERE u.member_id = member_attribute_values.member_id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
        AND EXISTS (
            SELECT 1 FROM team_manager.member_attributes ma
            WHERE ma.id = member_attribute_values.attribute_id
              AND ma.visible_to_player = TRUE
        )
    )
);
CREATE POLICY mav_insert ON team_manager.member_attribute_values FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (
        current_setting('app.current_role', true) = 'member'
        AND EXISTS (
            SELECT 1 FROM team_manager.users u
            WHERE u.member_id = member_attribute_values.member_id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
        AND EXISTS (
            SELECT 1 FROM team_manager.member_attributes ma
            WHERE ma.id = member_attribute_values.attribute_id
              AND ma.editable_by_player = TRUE
        )
    )
);
CREATE POLICY mav_update ON team_manager.member_attribute_values FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (
        current_setting('app.current_role', true) = 'member'
        AND EXISTS (
            SELECT 1 FROM team_manager.users u
            WHERE u.member_id = member_attribute_values.member_id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
        AND EXISTS (
            SELECT 1 FROM team_manager.member_attributes ma
            WHERE ma.id = member_attribute_values.attribute_id
              AND ma.editable_by_player = TRUE
        )
    )
);

-- ── Files RLS ────────────────────────────────────────────────────────────────

ALTER TABLE team_manager.files ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.files FORCE ROW LEVEL SECURITY;

CREATE POLICY files_select ON team_manager.files FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (visibility IN ('public', 'protected')
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);
CREATE POLICY files_insert ON team_manager.files FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);
CREATE POLICY files_update ON team_manager.files FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);
CREATE POLICY files_delete ON team_manager.files FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);

-- ── Events RLS ───────────────────────────────────────────────────────────────

ALTER TABLE team_manager.events ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_manager.events FORCE ROW LEVEL SECURITY;

-- Coordinators see all team events; members see protected events only; private = coordinator-only
CREATE POLICY events_select ON team_manager.events FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    OR (visibility = 'protected'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);
CREATE POLICY events_insert ON team_manager.events FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);
CREATE POLICY events_update ON team_manager.events FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);
CREATE POLICY events_delete ON team_manager.events FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
    OR (current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
);
