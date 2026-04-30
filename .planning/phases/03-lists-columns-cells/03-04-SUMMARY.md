---
phase: 03-lists-columns-cells
plan: "04"
subsystem: coach-cell-edit
tags: [coach, eav, cells, table, forms, upsert, validation]

dependency_graph:
  requires:
    - 03-01 (EAV schema, visibility.php, can_view_list/can_edit_cell)
    - 03-02 (list management, routing context)
  provides:
    - GET /coach/lists/{id} — full EAV table view with all players and columns
    - GET/POST /coach/lists/{id}/rows/{player_id}/edit — per-player cell edit form
  affects:
    - 03-05 (player cell edit — same EAV pattern, player role restrictions)

tech_stack:
  added: []
  patterns:
    - EAV cell map indexed by [player_id][column_id] for O(1) template lookups
    - Global-first column ORDER BY (list_id IS NULL) DESC
    - UPSERT ON CONFLICT (list_id, column_id, player_id) DO UPDATE
    - Boolean checkbox POST detection via isset() (unchecked = not in POST)
    - Type-safe validation per column data_type before any DB write

key_files:
  created:
    - src/coach/list_detail_handler.php
    - src/templates/coach/list_detail.php
    - src/coach/list_row_edit_handler.php
    - src/templates/coach/list_row_form.php
  modified: []

decisions:
  - Prepare UPSERT inside foreach loop — one prepared statement per column per save; acceptable for typical team sizes (< 30 columns)
  - Boolean false stored as '0' (not NULL) — consistent display in table (x icon vs blank)
  - Number cell cleared to NULL on empty string submit — clean distinction between "not set" and "0"
  - can_edit_cell() called twice in row edit handler: before render (fast-fail) and before POST (defense-in-depth)

metrics:
  duration: "~2min"
  completed: "2026-04-30"
  tasks_completed: 2
  files_created: 4
  files_modified: 0
---

# Phase 03 Plan 04: Coach List Detail and Row Cell Edit Summary

**One-liner:** EAV table view with global-first column ordering plus type-validated UPSERT cell editing for coaches.

## What Was Built

### Task 1: List detail handler and table template (`8dad869`)

`src/coach/list_detail_handler.php` — GET /coach/lists/{id}:
- `can_view_list($list_id)` ownership check returns 404 on failure
- Column query: `(list_id = ? OR (list_id IS NULL AND team_id = ?)) ORDER BY (list_id IS NULL) DESC` — global columns sort first
- Player query: all active players in team ordered by last_name, first_name
- Cell map built: `$cells[(int)$cell['player_id']][(int)$cell['column_id']] = $cell['value']`
- Flash messages: `?success=1` → "Zeile gespeichert.", `?error=` → danger alert

`src/templates/coach/list_detail.php`:
- Bootstrap `table-responsive` for horizontal scroll (D-04)
- Global column badge "G" in table headers
- Boolean values: check-lg (green) or x-lg (muted) icons; empty cells show blank (D-05)
- Each player row has "Bearbeiten" button to `/coach/lists/{id}/rows/{player_id}/edit` (CELL-04)
- Inline local column add form via `<details>` (D-10)
- Empty state messages for no players / no columns

### Task 2: Row edit handler and cell form template (`3a0aac5`)

`src/coach/list_row_edit_handler.php` — GET/POST /coach/lists/{id}/rows/{player_id}/edit:
- `can_edit_cell($list_id, $player_id)` checked before render (returns 403) and in POST block (defense-in-depth)
- Player verified: `WHERE id = ? AND team_id = ? AND role = 'player'`
- POST: `require_csrf()` enforced
- Type validation per column `data_type`:
  - `boolean`: `isset($_POST['cells'][$col_id]) ? '1' : '0'` — unchecked checkbox = false
  - `number`: `filter_var(FILTER_VALIDATE_INT || FILTER_VALIDATE_FLOAT)`; empty string → NULL
  - `text`: `mb_substr($raw_value, 0, 255)` truncation
- UPSERT: `ON CONFLICT (list_id, column_id, player_id) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()`
- Success: `redirect('/coach/lists/' . $list_id . '?success=1')` (PRG pattern)

`src/templates/coach/list_row_form.php`:
- Player name in card header
- Per-column inputs: `<input type="checkbox">` for boolean, `<input type="number" step="any">` for number, `<input type="text" maxlength="255">` for text
- Global column "G" badge on labels
- `csrf_field()` in form
- Speichern / Abbrechen buttons

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all data is wired to live database queries; no hardcoded placeholders.

## Self-Check: PASSED

Files verified:
- FOUND: src/coach/list_detail_handler.php
- FOUND: src/templates/coach/list_detail.php
- FOUND: src/coach/list_row_edit_handler.php
- FOUND: src/templates/coach/list_row_form.php

Commits verified:
- FOUND: 8dad869 (feat(03-04): list detail handler and table template)
- FOUND: 3a0aac5 (feat(03-04): coach row edit handler and cell form template)
