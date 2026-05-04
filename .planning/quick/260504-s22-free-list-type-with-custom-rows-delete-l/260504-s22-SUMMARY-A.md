---
phase: 260504-s22
plan: A
subsystem: lists
tags: [free-list, list-type, free-list-rows, cell-editing, rls, migration]
tech_stack:
  added: []
  patterns: [EAV-reuse-for-free-rows, two-step-no-js-delete, list-type-branching]
key_files:
  created: []
  modified:
    - src/db/connection.php
    - src/coach/list_create_handler.php
    - src/templates/coach/list_form.php
    - src/coach/list_detail_handler.php
    - src/templates/coach/list_detail.php
decisions:
  - "Drop cells.player_id FK to allow free_list_rows IDs to be stored there — app layer enforces ownership, RLS enforces visibility"
  - "list_type column defaults to 'member' so all existing lists are unaffected"
  - "Two-step delete confirmation is purely server-side (confirm=1 hidden param) — no JS required"
  - "Free lists use local columns only; global columns are suppressed in create form and handler"
metrics:
  duration_seconds: 592
  completed_date: "2026-05-04"
  tasks_completed: 3
  files_modified: 5
---

# Phase 260504-s22 Plan A: Free List Type with Custom Rows Summary

**One-liner:** Free list type added — moderators define custom row labels (not team members), reusing cells table with free_list_rows.id stored in player_id after dropping the FK.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | DB migration 006 — list_type + free_list_rows + drop cells FK | 75ab91e | src/db/connection.php |
| 2 | List create form — list_type radio, suppress global columns for free | d08ffa0 | src/templates/coach/list_form.php, src/coach/list_create_handler.php |
| 3 | Free list detail — add/delete rows, cell editing, two-step confirm | f83de22 | src/coach/list_detail_handler.php, src/templates/coach/list_detail.php |

## What Was Built

### Migration 006 (src/db/connection.php)
- Adds `lists.list_type VARCHAR(10) NOT NULL DEFAULT 'member' CHECK (list_type IN ('member', 'free'))` — idempotent via `information_schema` check
- Creates `free_list_rows` table (`id`, `list_id`, `label`, `position`, `created_at`) with index on `list_id`
- Drops `cells.player_id` FK to `users.id` — needed so free_list_rows IDs can be stored in the existing cells table without a FK violation
- Adds RLS policies `flr_select`, `flr_insert`, `flr_delete` on `free_list_rows`
- Defines constant `DB_HAS_LIST_TYPE` (bool) for conditional query building
- `db_init_schema()` updated: `list_type` column in lists CREATE TABLE, `player_id` in cells has no FK, `free_list_rows` table added
- `db_init_rls()` updated: `free_list_rows` RLS policies added

### List Create Form (Task 2)
- "Listentyp" radio group added above the name field: Mitgliederliste / Freie Liste
- Global columns picker hidden entirely when `list_type='free'` (PHP-side conditional, no JS)
- Handler reads and validates `list_type` from POST, inserts it into the `lists` row (guarded by `DB_HAS_LIST_TYPE`)
- Global columns linking block skipped for free lists
- `$list_type` passed back to template on GET and error re-render

### Free List Detail (Task 3)
- Handler fetches `list_type` in metadata query; branches on `$is_free_list`
- Free list path: loads `free_list_rows` sorted by position/created_at; local columns only; `$players = []`
- `action=add_row`: validates label non-empty, inserts into `free_list_rows`, redirects
- `action=delete_row` (first POST): verifies row belongs to list, re-renders with confirmation alert — no JS
- `action=delete_row` with `confirm=1` (second POST): deletes orphan cells then deletes row in a transaction
- `action=save_cells`: unified loop works for both member lists (over `$players`) and free lists (over `$free_rows`), upserts cells using row ID as `player_id`
- Template: free list block has add-row form, add-column form, table with Zeile/columns/Aktion columns, totals row, and all empty-state variants
- Member list path: completely unchanged

## Deviations from Plan

None — plan executed exactly as written. The FK constraint name detection (querying `information_schema.table_constraints` rather than hardcoding `cells_player_id_fkey`) was specified in the plan as the correct approach.

## Known Stubs

None — all data paths are wired. Free list rows are loaded from DB, cells are read from DB and saved back.

## Self-Check

Commits present:
- 75ab91e — feat(260504-s22-A): DB migration 006
- d08ffa0 — feat(260504-s22-A): list create form
- f83de22 — feat(260504-s22-A): free list detail

Files modified:
- src/db/connection.php — migration 006 added, db_init_schema/rls updated
- src/coach/list_create_handler.php — list_type handling added
- src/templates/coach/list_form.php — Listentyp radio added, global columns conditional
- src/coach/list_detail_handler.php — full free list branching logic
- src/templates/coach/list_detail.php — free list UI block added

## Self-Check: PASSED
