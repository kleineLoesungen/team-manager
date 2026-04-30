---
phase: 03-lists-columns-cells
plan: "05"
subsystem: player-list-view-cell-edit
tags: [player, eav, cells, table, visibility, authorization, forms, upsert]

dependency_graph:
  requires:
    - 03-01 (EAV schema, visibility.php, can_view_list/can_edit_cell, require_player)
    - 03-02 (player layout, render_player_page, routing)
    - 03-03 (list management and column setup)
    - 03-04 (coach cell edit patterns replicated for player side)
  provides:
    - GET /player/lists — public list overview for player (CELL-03)
    - GET /player/lists/{id} — all rows visible; own row edit button (CELL-04)
    - GET/POST /player/lists/{id}/rows/{player_id}/edit — player edits own row only (CELL-01)
  affects: []

tech_stack:
  added: []
  patterns:
    - Player visibility filter: explicit WHERE visibility='public' + can_view_list() RLS defense-in-depth
    - CELL-04 own-row detection: (int)$player['id'] === $current_user_id in template
    - Double ownership check: can_edit_cell() + explicit $_SESSION['user_id'] !== $player_id (D-15)
    - EAV cell map, UPSERT, type validation identical to coach side (behavioral consistency)
    - table-primary highlight on own row; 'Ich' badge for player identification

key_files:
  created:
    - src/player/lists_handler.php
    - src/player/list_detail_handler.php
    - src/player/list_row_edit_handler.php
    - src/templates/player/lists.php
    - src/templates/player/list_detail.php
    - src/templates/player/list_row_form.php
  modified: []

decisions:
  - Explicit WHERE visibility='public' in lists_handler even though RLS filters — defense-in-depth at SQL layer
  - Double ownership check in list_row_edit_handler: can_edit_cell() covers public+own; explicit session check is an additional guard per D-15
  - List metadata re-fetched with AND visibility='public' constraint in row edit handler — prevents access even if can_edit_cell() has a bug
  - Boolean false stored as '0' — consistent with coach side; display shows x icon vs blank
  - Own row highlighted with table-primary CSS class + 'Ich' badge for immediate visual identification

metrics:
  duration: "~5min"
  completed: "2026-04-30"
  tasks_completed: 2
  files_created: 6
  files_modified: 0
---

# Phase 03 Plan 05: Player List View and Cell Edit Summary

**One-liner:** Player-facing list overview (public only) and table view with all-rows visibility, own-row edit button, and double-ownership-checked cell UPSERT.

## What Was Built

### Task 1: Player list overview and list detail view (`9990669`)

`src/player/lists_handler.php` — GET /player/lists:
- `require_player()` sets RLS team context with role='player' and user_id
- SQL query filters `WHERE l.visibility = 'public'` explicitly (CELL-03 defense-in-depth)
- Renders public list cards with column count
- Flash success via `?success=1`

`src/player/list_detail_handler.php` — GET /player/lists/{id}:
- `can_view_list($list_id)` → returns 404 if not public or wrong team (CELL-03)
- Column query: global-first `ORDER BY (list_id IS NULL) DESC` — same as coach side
- Player query: ALL active players in team — CELL-04 (all rows visible regardless of who is logged in)
- Cell map built: `$cells[(int)$cell['player_id']][(int)$cell['column_id']]`
- `$current_user_id = (int)$_SESSION['user_id']` passed to template for edit button logic

`src/templates/player/lists.php`:
- Empty state message when no public lists exist
- Card grid: list name, "Öffentlich" badge, column count, "Öffnen" button

`src/templates/player/list_detail.php`:
- Bootstrap `table-responsive` for mobile horizontal scroll (D-04)
- `$is_own_row = (int)$player['id'] === $current_user_id` — used in four places
- Own row highlighted with `class="table-primary"` and "Ich" badge
- Edit button rendered ONLY when `$is_own_row` is true (CELL-04 + CELL-01)
- Boolean values: check-lg (green) or x-lg (muted); empty cells show blank

### Task 2: Player row edit handler and form template (`9d19a9f`)

`src/player/list_row_edit_handler.php` — GET/POST /player/lists/{id}/rows/{player_id}/edit:
- `can_edit_cell($list_id, $player_id)` checked first: returns 403 if not own row or not public list
- Explicit `$_SESSION['user_id'] !== $player_id` check: returns 403 (D-15 defense-in-depth)
- Player verified with `AND role = 'player' AND is_active = TRUE` (active player only)
- List re-fetched with `AND visibility = 'public'` — returns 404 if not public
- POST: `require_csrf()` enforced; can_edit_cell() + session check re-verified
- Type validation per column `data_type`:
  - `boolean`: `isset($_POST['cells'][$col_id]) ? '1' : '0'`
  - `number`: `filter_var(FILTER_VALIDATE_INT || FILTER_VALIDATE_FLOAT)`; empty → NULL
  - `text`: `mb_substr($raw_value, 0, 255)` truncation
- UPSERT: `ON CONFLICT (list_id, column_id, player_id) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()`
- Redirect to `/player/lists/{id}?success=1` on success (PRG pattern)

`src/templates/player/list_row_form.php`:
- "Meine Zeile" badge in card header (player context)
- `csrf_field()` in form
- Per-column inputs: checkbox for boolean, number input (step="any") for number, text input (maxlength="255") for text
- Speichern / Abbrechen buttons with min-touch class

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all data is wired to live database queries; no hardcoded placeholders.

## Self-Check: PASSED

Files verified:
- FOUND: src/player/lists_handler.php
- FOUND: src/player/list_detail_handler.php
- FOUND: src/player/list_row_edit_handler.php
- FOUND: src/templates/player/lists.php
- FOUND: src/templates/player/list_detail.php
- FOUND: src/templates/player/list_row_form.php

Commits verified:
- FOUND: 9990669 (feat(03-05): player list overview and list detail view)
- FOUND: 9d19a9f (feat(03-05): player row edit handler and cell edit form)
