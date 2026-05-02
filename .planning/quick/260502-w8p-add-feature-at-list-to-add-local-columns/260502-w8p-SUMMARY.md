---
phase: quick
plan: 260502-w8p
subsystem: columns
tags: [coach-only, columns, rls, migration, player-visibility]
dependency_graph:
  requires: []
  provides: [LOCAL-COACH-ONLY]
  affects: [coach/list_detail, player/list_detail, columns RLS policy]
tech_stack:
  added: []
  patterns: [idempotent-migration, rls-drop-recreate, php-level-filter-plus-rls-defense-in-depth]
key_files:
  created: []
  modified:
    - src/db/connection.php
    - database/schema.sql
    - database/rls_policies.sql
    - src/coach/list_column_create_handler.php
    - src/coach/list_detail_handler.php
    - src/templates/coach/list_detail.php
    - src/player/list_detail_handler.php
decisions:
  - "maybe_migrate_db() uses DROP POLICY IF EXISTS + recreate for idempotent RLS migration"
  - "PHP-level filter (coach_only = FALSE in WHERE) is authoritative; RLS is defense-in-depth"
  - "Global columns (list_id IS NULL) have no coach_only concept — only local columns filtered"
metrics:
  duration: ~10 minutes
  completed: "2026-05-02T21:17:01Z"
  tasks_completed: 3
  tasks_total: 3
  files_modified: 7
---

# Quick Task 260502-w8p: Coach-Only Local Columns — Summary

**One-liner:** `coach_only BOOLEAN` flag on local list columns — coaches mark columns "Nur für Trainer"; player view silently omits them via PHP filter and RLS defense-in-depth.

---

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | DB migration + DDL + RLS update | `1cf8356` | `src/db/connection.php`, `database/schema.sql`, `database/rls_policies.sql` |
| 2 | Coach create form + handler + badge | `b7f152b` | `src/templates/coach/list_detail.php`, `src/coach/list_column_create_handler.php`, `src/coach/list_detail_handler.php` |
| 3 | Player column query — exclude coach_only | `6059ad2` | `src/player/list_detail_handler.php` |

---

## What Was Built

### DB / Migration
- `columns.coach_only BOOLEAN NOT NULL DEFAULT FALSE` added to the DDL in `db_init_schema()` and `database/schema.sql`
- `maybe_migrate_db()` function added to `src/db/connection.php` — runs on every boot with idempotent `ADD COLUMN IF NOT EXISTS` and `DROP POLICY IF EXISTS` + recreate of the `columns_visibility_select` RLS policy
- `get_db()` now calls `maybe_migrate_db($pdo)` immediately after `maybe_init_db($pdo)`

### RLS Policy Update
- `columns_visibility_select` updated in all three sources (`db_init_rls()`, `database/rls_policies.sql`, and `maybe_migrate_db()` migration): the player-visible local-column branch now includes `AND coach_only = FALSE`
- Coach branch is unchanged — coaches always see all columns for their team

### Coach Interface
- "Nur für Trainer" checkbox added to the "Lokale Spalte hinzufügen" form in `list_detail.php`
- Column header loop shows a red `bg-danger` "T" badge on any local column with `coach_only = TRUE`
- `list_detail_handler.php` column query now selects `c.coach_only` to supply the badge condition
- `list_column_create_handler.php` reads `coach_only` from POST and includes it in the INSERT

### Player Interface
- `list_detail_handler.php` column query: local-column branch changed from `c.list_id = ?` to `(c.list_id = ? AND c.coach_only = FALSE)`, silently omitting coach-only columns
- Global columns (`list_id IS NULL`) are unaffected

---

## Decisions Made

| Decision | Rationale |
|----------|-----------|
| `maybe_migrate_db()` uses DROP POLICY IF EXISTS + recreate | Cannot use `ALTER POLICY` with a `CREATE ... IF NOT EXISTS` equivalent; drop+recreate is always idempotent |
| PHP query filter is authoritative, RLS is defense-in-depth | Consistent with existing pattern in the codebase (can_edit_cell, visibility checks); single clear place to audit |
| Global columns have no `coach_only` concept | Global columns are already team-wide structural columns; restricting them to coaches would break the stats model |

---

## Deviations from Plan

None — plan executed exactly as written.

---

## Known Stubs

None. All data paths are fully wired.

---

## Self-Check

**Files created/modified:**
- `src/db/connection.php` — FOUND
- `database/schema.sql` — FOUND
- `database/rls_policies.sql` — FOUND
- `src/coach/list_column_create_handler.php` — FOUND
- `src/coach/list_detail_handler.php` — FOUND
- `src/templates/coach/list_detail.php` — FOUND
- `src/player/list_detail_handler.php` — FOUND

**Commits verified:**
- `1cf8356` — FOUND
- `b7f152b` — FOUND
- `6059ad2` — FOUND

## Self-Check: PASSED
