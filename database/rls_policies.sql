-- PostgreSQL Row-Level Security Policies
-- Apply AFTER schema.sql has been run.
-- Defense-in-depth: application layer ALSO enforces team_id checks.

SET search_path TO team_manager, public;

-- Enable RLS on users table
ALTER TABLE users ENABLE ROW LEVEL SECURITY;

-- Only superuser/app role bypasses RLS (for login lookup across teams)
-- Regular queries filtered by app.current_team_id session variable

-- SELECT: users can only see members of their own team
CREATE POLICY team_isolation_users_select ON users
    FOR SELECT
    USING (
        team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    );

-- INSERT: new users must belong to the current team context
CREATE POLICY team_isolation_users_insert ON users
    FOR INSERT
    WITH CHECK (
        team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    );

-- UPDATE: users can only update rows within their own team
CREATE POLICY team_isolation_users_update ON users
    FOR UPDATE
    USING (
        team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    );

-- Teams table: no RLS needed (admin manages all teams, coaches only query their own via team_id FK)
-- Teams are referenced by team_id in users; no cross-team leakage risk at this layer.
