---
phase: quick
plan: 260505-8ph
subsystem: database/rls
tags: [rls, postgresql, columns, migration, security]
dependency_graph:
  requires: []
  provides: [columns_delete RLS policy]
  affects: [database/rls_policies.sql, src/db/connection.php]
tech_stack:
  added: []
  patterns: [idempotent boot migration, pg_policies existence check, try/catch RLS]
key_files:
  modified:
    - database/rls_policies.sql
    - src/db/connection.php
decisions:
  - "Migration 007 uses pg_policies check for idempotency so repeated boots are no-ops"
  - "try/catch in Migration 007 ensures shared hosts with restricted CREATE POLICY permissions fail silently"
  - "No try/catch in db_init_rls() — only runs on fresh schema where app user owns objects"
metrics:
  duration: "5 minutes"
  completed: "2026-05-05"
  tasks_completed: 2
  files_modified: 2
---

# Quick Task 260505-8ph: Add Missing columns_delete RLS Policy

**One-liner:** Added columns_delete PostgreSQL RLS policy in all three locations — canonical SQL, fresh-install function, and idempotent boot migration with permission-safe try/catch.

## Problem

The `columns` table had FORCE ROW LEVEL SECURITY but no DELETE policy. PostgreSQL default-deny silently matched 0 rows on every moderator DELETE, making column deletion appear to succeed while nothing was actually removed.

## Changes

### Task 1: Canonical SQL + fresh-install function

**database/rls_policies.sql** — Added `columns_delete` policy block after `columns_insert` (line 138):

```sql
CREATE POLICY columns_delete ON columns
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );
```

**src/db/connection.php — db_init_rls()** — Added matching `CREATE POLICY columns_delete` exec() call after `columns_insert` (line 574).

Commit: `650c331`

### Task 2: Boot migration

**src/db/connection.php — maybe_migrate_db()** — Added Migration 007 block (line 366):

- Checks `pg_policies` for policy existence before attempting CREATE (idempotent)
- Wrapped in try/catch — permission errors on shared hosts are logged, not fatal
- Runs on every boot; no-op once policy exists

Commit: `92f196f`

## Verification

- `grep -c "columns_delete" database/rls_policies.sql` → 1
- `grep -c "columns_delete" src/db/connection.php` → 5 (comment + pg_policies check + CREATE in migration 007 + error_log + CREATE in db_init_rls)
- `php -l src/db/connection.php` → No syntax errors detected

## Deviations from Plan

None - plan executed exactly as written.

## Known Stubs

None.

## pgAdmin Snippet (Production Manual Apply)

If Migration 007 cannot run due to permissions, apply via pgAdmin:

```sql
SET search_path TO manager, public;
DROP POLICY IF EXISTS columns_delete ON columns;
CREATE POLICY columns_delete ON columns
    FOR DELETE
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'moderator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );
```

## Self-Check: PASSED

Files modified:
- FOUND: /Users/sebastianwiller/Documents/github/team-manager/database/rls_policies.sql
- FOUND: /Users/sebastianwiller/Documents/github/team-manager/src/db/connection.php

Commits:
- FOUND: 650c331
- FOUND: 92f196f
