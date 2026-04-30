---
phase: 03-lists-columns-cells
plan: "01"
subsystem: database-schema
tags: [schema, rls, visibility, eav, php]

dependency_graph:
  requires: []
  provides:
    - EAV tables lists/columns/cells with FK constraints
    - RLS visibility policies for lists/columns/cells
    - can_view_list() and can_edit_cell() PHP helpers
  affects:
    - All Phase 3 plans (03-02 through 03-05) depend on these tables and helpers

tech_stack:
  added: []
  patterns:
    - EAV (Entity-Attribute-Value) with list_id IS NULL for global columns
    - Admin-context bypass pattern for PHP visibility checks
    - app.current_role + app.current_user_id in PostgreSQL session GUCs for RLS

key_files:
  created:
    - src/db/visibility.php
  modified:
    - database/schema.sql
    - database/rls_policies.sql
    - src/db/connection.php
    - src/auth/session.php

decisions:
  - "EAV global columns use list_id IS NULL as flag (no separate is_global boolean)"
  - "Visibility helpers use admin-context bypass with immediate reset (not RLS-filtered)"
  - "can_edit_cell() returns true for coaches on all visibility states per CELL-03"
  - "set_team_context() extended with role and user_id params for Phase 3 RLS"

metrics:
  duration: "~10 minutes"
  completed: "2026-04-30"
  tasks_completed: 3
  files_modified: 5
---

# Phase 3 Plan 1: EAV Schema, Visibility RLS & PHP Helpers Summary

**One-liner:** EAV schema (lists/columns/cells) with visibility-aware RLS policies and PHP can_view_list()/can_edit_cell() helpers using admin-context bypass for authoritative access checks.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Extend schema.sql with EAV tables | 1dc853a | database/schema.sql |
| 2 | Add visibility RLS policies for lists/columns/cells | ffcba97 | database/rls_policies.sql, src/db/connection.php, src/auth/session.php |
| 3 | Create src/db/visibility.php helpers | 6ab60af | src/db/visibility.php |

## What Was Built

### database/schema.sql
Three new EAV tables appended after the existing `users` table:
- `lists`: team-scoped, visibility CHECK ('public'/'protected'/'private'), updated_at timestamp
- `columns`: team-scoped, optional list_id (NULL = global), data_type CHECK ('boolean'/'number'/'text'), sort_order, is_active
- `cells`: EAV values stored as TEXT, UNIQUE(list_id, column_id, player_id), ON DELETE CASCADE from all parents

All three tables have the required performance indexes.

### database/rls_policies.sql
Six new RLS policies covering SELECT/INSERT/UPDATE:
- `lists_visibility_select`: coaches see all team lists; players see only public lists
- `lists_insert` / `lists_update`: admin or coach only
- `columns_visibility_select`: coach sees all; players see global columns + public list columns
- `columns_insert`: admin or coach only
- `cells_visibility_select`: inherits parent list visibility
- `cells_insert` / `cells_ownership_update`: players restricted to own row in public lists

### src/db/visibility.php
Two authoritative application-layer access functions:
- `can_view_list(int $list_id): bool` — coaches see all team lists; players see public only
- `can_edit_cell(int $list_id, int $player_id): bool` — coaches have full access (CELL-03); players restricted to own row in public lists (CELL-01)

Both functions use `set_admin_context()` for the lookup, then immediately `reset_rls_context()` + `set_team_context()` to restore session context.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Security] set_team_context() did not set app.current_role or app.current_user_id**
- **Found during:** Task 2 (before writing RLS policies)
- **Issue:** The RLS policies for Phase 3 reference `app.current_role` and `app.current_user_id` for player cell ownership checks. Neither was set by `set_team_context()` or `reset_rls_context()`, making the RLS policies non-functional.
- **Fix:** Extended `set_team_context(PDO $pdo, int $team_id)` to `set_team_context(PDO $pdo, int $team_id, ?string $role = null, ?int $user_id = null)` — sets all four GUC values in a single SQL exec call. Updated `reset_rls_context()` to clear all four settings. Updated `require_coach()` in session.php to pass role='coach' and user_id. Added `require_player()` to session.php for Phase 3 player routes.
- **Files modified:** `src/db/connection.php`, `src/auth/session.php`
- **Commit:** ffcba97

**2. [Rule 2 - Security] visibility.php restores full team context (role + user_id) after admin bypass**
- **Found during:** Task 3 (writing visibility.php)
- **Issue:** The plan template showed `set_team_context($pdo, (int)$_SESSION['team_id'])` without role/user_id on context restore, which would reset role and user_id to empty string after the admin-bypass lookup.
- **Fix:** Both `can_view_list()` and `can_edit_cell()` call `set_team_context($pdo, (int)$_SESSION['team_id'], $_SESSION['role'], (int)$_SESSION['user_id'])` on restore to maintain full RLS context.
- **Files modified:** `src/db/visibility.php`
- **Commit:** 6ab60af (incorporated in Task 3 commit)

## Known Stubs

None — this plan is pure schema + policy + helper functions. No UI rendering, no data stubs.

## Self-Check: PASSED
