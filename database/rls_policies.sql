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
CREATE POLICY team_isolation_users_select ON users
    FOR SELECT
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
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

-- Cells INSERT: admin and coach can insert any cell; player can only insert their own cell in public lists
CREATE POLICY cells_insert ON cells
    FOR INSERT
    WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
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

-- Cells UPDATE: admin and coach can update any cell; player can only update their own cell in public lists
CREATE POLICY cells_ownership_update ON cells
    FOR UPDATE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
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
