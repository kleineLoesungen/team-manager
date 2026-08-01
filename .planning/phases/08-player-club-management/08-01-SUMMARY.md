---
phase: 08-player-club-management
plan: 01
subsystem: database
tags: [postgresql, rls, migration, schema, eav]

requires:
  - phase: 07-live-ticker
    provides: ticker_members table (last table before Phase 8 in db_init_schema)

provides:
  - Migration 012 in maybe_migrate_db(): 7 new tables in FK order, coordinator_teams backfill, idempotent RLS
  - db_init_schema() updated: clubs, player_attribute_groups, players, team_memberships, coordinator_teams, player_attributes, player_attribute_values
  - db_init_rls() updated: full RLS policy sets for all 7 new tables
  - database/schema.sql: Phase 8 DDL for fresh installs and reference
  - team_action_handler.php: multi-team coordinator deletion bug fixed

affects:
  - 08-02 (login/team-picker, reads coordinator_teams)
  - 08-03 (admin clubs CRUD, uses clubs table)
  - 08-04 (admin players CRUD, uses players + team_memberships)
  - 08-05 (coordinator player profiles, uses player_attribute_values)
  - 08-06 (member player profile, uses player_attribute_values RLS)
  - 08-07 (club-wide stats, uses team_memberships + set_admin_context)

tech-stack:
  added: []
  patterns:
    - "Migration 012 pattern: gate on clubs table existence; CREATE tables in FK order; backfill before RLS; ALTER TABLE outside gate (always idempotent)"
    - "Partial unique index WHERE left_at IS NULL: one active membership per (player/user, team) pair — DB-enforced, race-safe"
    - "Drop-and-recreate RLS in maybe_migrate_db: DROP POLICY IF EXISTS + CREATE POLICY for idempotency on existing installs"

key-files:
  created:
    - database/schema.sql (Phase 8 section appended)
  modified:
    - src/db/connection.php
    - src/admin/team_action_handler.php

key-decisions:
  - "coordinator_teams backfill runs inside if (!$clubs_exists) block, before RLS ENABLE — no team context at migration time"
  - "ALTER TABLE users ADD COLUMN player_id + phone placed outside $clubs_exists gate so they apply to both fresh and existing installs on every boot"
  - "users.team_id kept intact — not removed; coordinator_teams is additive, not a replacement (preserves all existing RLS patterns)"
  - "team deletion now guards against destroying multi-team coordinators via NOT EXISTS subquery on coordinator_teams"

patterns-established:
  - "Pattern: players scoped to teams via team_memberships subquery (no direct team_id on players table)"
  - "Pattern: coordinator_teams select policy scopes by user_id, not team_id — coordinator sees own rows only"
  - "Pattern: player_attribute_values most complex RLS — coordinator scope via team_memberships subquery; member scope via users.player_id + visible_to_player flag"

requirements-completed: [CLUB-01, CLUB-02, CLUB-03, CLUB-04, CLUB-05, CLUB-06, CLUB-09]

duration: 41min
completed: 2026-08-01
---

# Phase 8 Plan 01: Database Foundation Summary

**PostgreSQL Migration 012 adds 7 new tables (clubs, players, team_memberships, coordinator_teams, player_attribute_groups, player_attributes, player_attribute_values) with partial unique indexes, full RLS, and coordinator_teams backfill from existing coordinators**

## Performance

- **Duration:** 41 min
- **Started:** 2026-08-01T08:14:23Z
- **Completed:** 2026-08-01T08:55:14Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- Migration 012 in `maybe_migrate_db()`: creates all 7 tables in FK dependency order, backfills `coordinator_teams` from existing coordinators before enabling RLS, applies idempotent drop-and-recreate RLS policies
- `db_init_schema()` and `db_init_rls()` updated for fresh installs (all 7 tables + full RLS policy sets)
- `database/schema.sql` now documents all Phase 8 tables with correct DDL, indexes, and migration comment for existing databases
- Team deletion bug fixed: coordinators managing multiple teams are no longer destroyed when one of their teams is deleted

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration 012 in connection.php** - `a8fbeef` (feat)
2. **Task 2: Update database/schema.sql with Phase 8 tables** - `f597791` (feat)
3. **Task 3: Fix team deletion bug — preserve multi-team coordinators** - `732a9d4` (fix)

## Files Created/Modified

- `src/db/connection.php` — Three additions: db_init_schema() (7 new tables), db_init_rls() (7 RLS policy sets), maybe_migrate_db() Migration 012 block
- `database/schema.sql` — Phase 8 section appended: all 7 tables with schema-qualified names, indexes, partial unique indexes, migration comment
- `src/admin/team_action_handler.php` — Delete action now guards against removing multi-team coordinators with NOT EXISTS subquery

## Decisions Made

- coordinator_teams backfill runs inside the `if (!$clubs_exists)` gate, before ENABLE ROW LEVEL SECURITY — at migration time there is no team context, so RLS must be off when the INSERT runs
- ALTER TABLE users ADD COLUMN player_id + phone placed outside the `$clubs_exists` gate so they are applied idempotently on every boot (safe on both fresh and existing installs)
- users.team_id kept intact — coordinator_teams is purely additive; this preserves every existing RLS policy and session pattern without any modifications
- Partial unique index `WHERE left_at IS NULL` chosen over application-layer checks (race-safe, DB-enforced)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required. Migration runs automatically on next request to the PHP application.

## Next Phase Readiness

- All 7 Phase 8 tables exist with correct FK constraints, indexes, and RLS policies
- coordinator_teams is populated from existing coordinators — existing users can log in immediately
- Plan 08-02 (multi-team coordinator login flow) can now read coordinator_teams at login
- Plans 08-03 through 08-07 have the schema foundation they depend on

---
*Phase: 08-player-club-management*
*Completed: 2026-08-01*
