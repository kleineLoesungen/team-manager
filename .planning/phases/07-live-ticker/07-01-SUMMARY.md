---
phase: 07-live-ticker
plan: 01
subsystem: database
tags: [postgresql, rls, schema, ticker, live-ticker]

# Dependency graph
requires:
  - phase: 06-calendar
    provides: existing schema + RLS pattern in connection.php that this plan extends

provides:
  - 4 new PostgreSQL tables: tickers, ticker_tags, ticker_messages, ticker_members
  - RLS policies for all 4 ticker tables in database/rls_policies.sql
  - db_init_schema() extended with ticker table DDL using {$s} prefix
  - db_init_rls() extended with ticker RLS policies using {$s} prefix

affects: [07-02, 07-03, 07-04, 07-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Ticker tables follow existing team_manager schema: CREATE TABLE IF NOT EXISTS with schema-qualified names"
    - "RLS policies: coordinator full CRUD, open SELECT for tickers/tags (public endpoint), subquery via tickers.team_id for messages"
    - "ticker_members join table: PHP + RLS dual enforcement of member posting access"
    - "FORCE ROW LEVEL SECURITY wrapped in try/catch (non-fatal on shared hosting)"

key-files:
  created: []
  modified:
    - database/schema.sql
    - database/rls_policies.sql
    - src/db/connection.php

key-decisions:
  - "ticker_messages SELECT policy uses subquery through tickers.team_id (no direct team_id column) — consistent with cells pattern through lists"
  - "ticker_members SELECT restricted to coordinator + member roles — public endpoints only need tickers/messages, not the membership table"
  - "ticker_tags SELECT open to any team context (no role check) — needed for public post forms showing available tags"

patterns-established:
  - "Phase 7 tables use {$s} prefix in db_init_schema/db_init_rls matching all prior phases"
  - "FORCE RLS wrapped in try/catch with error_log for shared hosting compatibility"

requirements-completed: [TICKER-01, TICKER-03]

# Metrics
duration: 15min
completed: 2026-07-26
---

# Phase 07 Plan 01: DB Schema — Live-Ticker Tables Summary

**Four ticker tables (tickers, ticker_tags, ticker_messages, ticker_members) added to schema.sql and rls_policies.sql with idempotent auto-init in db_init_schema() and db_init_rls()**

## Performance

- **Duration:** ~15 min (continuation agent)
- **Started:** 2026-07-26T16:14:42Z
- **Completed:** 2026-07-26T16:19:20Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- All 4 ticker tables with correct columns, constraints, indexes in database/schema.sql and db_init_schema()
- Complete RLS policy set for all 4 ticker tables in database/rls_policies.sql and db_init_rls()
- Coordinator-gated writes, open SELECT for public endpoints (tickers, messages, tags), member-gated posting via ticker_members

## Task Commits

Each task was committed atomically:

1. **Task 1: Add 4 ticker tables to schema.sql and db_init_schema()** - `7ce59db` (feat)
2. **Task 2: Add RLS policies to rls_policies.sql and db_init_rls()** - `f4d9d42` (feat)

**Plan metadata:** (this commit — docs)

## Files Created/Modified
- `database/schema.sql` - Phase 7 block appended: ticker_tags, tickers, ticker_messages, ticker_members tables + indexes
- `database/rls_policies.sql` - Phase 7 RLS block appended: ENABLE/FORCE + all CRUD policies for 4 ticker tables
- `src/db/connection.php` - db_init_schema() and db_init_rls() both extended with identical ticker DDL and policies using {$s} prefix

## Decisions Made
- ticker_messages SELECT policy routes through tickers.team_id via subquery (messages have no direct team_id column) — mirrors the cells→lists pattern
- ticker_members SELECT is restricted to coordinator + member roles; unauthenticated public readers only need tickers and messages
- FORCE ROW LEVEL SECURITY on all 4 new tables is wrapped in try/catch for shared hosting compatibility, matching the existing pattern for free_list_rows

## Deviations from Plan

None - plan executed exactly as written. The rls_policies.sql file already contained the Phase 7 block when this continuation agent started (the previous agent completed that part before being cut off); only db_init_rls() in connection.php was missing.

## Issues Encountered

The previous agent was cut off mid-task 2. The rls_policies.sql file had already been updated; only the PHP counterpart in connection.php was missing. Continuation was straightforward.

## User Setup Required

None - no external service configuration required. The ticker tables are created automatically by db_init_schema() on first boot (fresh DB) or can be applied manually to an existing DB using the SQL blocks in database/schema.sql and database/rls_policies.sql.

## Next Phase Readiness
- All 4 ticker tables and their RLS policies are in place; Phase 7 plans 02–05 can proceed
- No blockers

---
*Phase: 07-live-ticker*
*Completed: 2026-07-26*
