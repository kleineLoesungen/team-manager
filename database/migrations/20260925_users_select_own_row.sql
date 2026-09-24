-- database/migrations/20260925_users_select_own_row.sql
-- Team Manager - RLS fix: a user must always be able to read their own row.
--
-- >>> SET YOUR SCHEMA ON THE `SET search_path` LINE BELOW BEFORE RUNNING. <<<
--     Production uses `manager`. Docker/dev uses `team_manager`.
--
-- PROBLEM
-- team_isolation_users_select allowed a row only when
--     users.team_id = app.current_team_id
-- but users.team_id is the ORIGIN team, while app.current_team_id is the team the
-- coordinator is currently working in. coordinator_teams - not users.team_id - is the
-- truth for active membership, so for any coordinator working outside their origin team
-- the two differ and the policy hid their OWN user row.
--
-- Two things broke as a result, both silently and with no error:
--   1. /coordinator/profile returned no row at all - blank contact data AND no calendar
--      tokens, because the whole query hinged on reading users.
--   2. members_select's "own record" arm subqueries users, so RLS on users propagated
--      and the coordinator's own member record became invisible too.
--
-- FIX
-- Widen the policy with `OR id = app.current_user_id`. "A user can always read their own
-- row" is an invariant the rest of the schema already assumes.
--
-- SCOPE NOTE: this grants a session read access to its own users row, which includes
-- password_hash. That is not an escalation - it is a bcrypt hash belonging to the
-- already-authenticated session - but it is a real widening, so it is called out rather
-- than buried. Team isolation is untouched: no user gains visibility of any OTHER user.
--
-- Safe to re-run: ALTER POLICY simply restates the definition.
--
-- Usage: psql -h <host> -U <owner> -d <database> -f <this file>

SET search_path TO manager, public;   -- <<< CHANGE IF YOUR SCHEMA DIFFERS

-- [DDL - needs table OWNER]
ALTER POLICY team_isolation_users_select ON users
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        OR id      = NULLIF(current_setting('app.current_user_id', true), '')::integer
    );

COMMIT;

-- Verification: should list all three arms, including `id = ...`.
SELECT qual AS users_select_policy
FROM pg_policies
WHERE schemaname = current_schema() AND tablename = 'users' AND cmd = 'SELECT';
