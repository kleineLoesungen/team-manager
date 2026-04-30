---
phase: 03-lists-columns-cells
plan: "03"
subsystem: coach-list-management
tags: [lists, columns, eav, coach, php, bootstrap, prd]

dependency_graph:
  requires:
    - 03-01 (EAV schema: lists/columns/cells tables + RLS)
    - 03-02 (coach layout with lists/columns nav items)
  provides:
    - GET /coach/lists — list overview with Bootstrap card grid
    - GET/POST /coach/lists/create — list creation form
    - GET/POST /coach/lists/{id}/settings — visibility change
    - GET /coach/columns — global columns overview with inline form
    - POST /coach/columns/create — global column creation (boolean/number only)
    - POST /coach/lists/{id}/columns/create — local column addition (all types)
  affects:
    - 03-04 (list detail view depends on lists/columns created here)
    - 03-05 (player views depend on lists with proper visibility states)
    - Phase 4 statistics (global column type constraint enforced here protects stat aggregation)

tech_stack:
  added: []
  patterns:
    - Bootstrap card grid with visibility badges using PHP match()
    - PRG (POST-redirect-GET) with ?error= and ?success= query params
    - Inline form embedded in overview template (columns.php)
    - EAV global column flag via list_id IS NULL (no association table)

key_files:
  created:
    - src/coach/lists_handler.php
    - src/coach/list_create_handler.php
    - src/coach/list_settings_handler.php
    - src/coach/columns_handler.php
    - src/coach/columns_create_handler.php
    - src/coach/list_column_create_handler.php
    - src/templates/coach/lists.php
    - src/templates/coach/list_form.php
    - src/templates/coach/columns.php
    - src/templates/coach/column_form.php
  modified: []

decisions:
  - "Global column create form embedded inline in columns.php overview page (no separate /create page) — reduces navigation steps for coaches managing column setup"
  - "Global column checkboxes in list_form.php are informational only (no DB association) — global columns are team-wide by EAV design (list_id IS NULL), so no per-list join table is needed"
  - "list_settings_handler.php does not require src/db/visibility.php — removed that require_once since visibility.php wasn't needed and the import caused a potential load error"

metrics:
  duration: "8 minutes"
  completed_date: "2026-04-30"
  tasks_completed: 2
  files_created: 10
  files_modified: 0
---

# Phase 03 Plan 03: Coach List and Column Management Summary

**One-liner:** Coach list CRUD with Bootstrap card grid visibility badges plus global/local column creation enforcing EAV no-text constraint for global columns.

## What Was Built

### Task 1 — List management handlers and templates (commit: 5e4a738)

- `src/coach/lists_handler.php` — fetches all team lists with column counts (LEFT JOIN covering both global and local columns), renders Bootstrap card grid
- `src/coach/list_create_handler.php` — PRG handler: GET shows form with available global columns, POST validates and INSERTs into `lists`, redirects to list detail on success
- `src/coach/list_settings_handler.php` — verifies list ownership, GET shows visibility select with current value pre-selected, POST UPDATEs visibility
- `src/templates/coach/lists.php` — card grid with `card-header` containing list name + visibility badge (PHP `match()` for badge class/label), card footer with Öffnen + Einstellungen buttons
- `src/templates/coach/list_form.php` — three-section form: name input, visibility `<select>` with three options, global columns info (informational checkboxes, pre-checked)

Covers: LIST-01, LIST-04, LIST-05

### Task 2 — Column management handlers and templates (commit: cf96992)

- `src/coach/columns_handler.php` — fetches global columns (`list_id IS NULL`) for team, renders overview table + inline create form
- `src/coach/columns_create_handler.php` — POST-only handler; validates `data_type IN ('boolean', 'number')` (text rejected to protect Phase 4 statistics), INSERTs with `list_id = NULL`
- `src/coach/list_column_create_handler.php` — verifies list ownership, validates `data_type IN ('boolean', 'number', 'text')`, INSERTs with explicit `list_id`
- `src/templates/coach/columns.php` — overview table + embedded create form with only boolean/number options; includes note "Text-Spalten sind nur in lokalen Listen-Spalten erlaubt"
- `src/templates/coach/column_form.php` — minimal stub for structural consistency

Covers: LIST-02, LIST-03

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Removed erroneous `require_once ROOT_PATH . '/src/db/visibility.php'` from list_settings_handler.php**
- **Found during:** Task 1 implementation review
- **Issue:** The plan's code sample for `list_settings_handler.php` included `require_once ROOT_PATH . '/src/db/visibility.php'` but the handler doesn't use any functions from that file — no `can_view_list()` or `can_edit_cell()` calls. Loading it would add an unnecessary dependency and could fail if the path changes.
- **Fix:** Omitted the require_once entirely — handler uses only standard PDO and session patterns
- **Files modified:** src/coach/list_settings_handler.php
- **Commit:** 5e4a738

None other — plan executed as specified.

## Known Stubs

- `src/templates/coach/column_form.php` — empty stub file; global column creation is embedded in `columns.php`. This is intentional per the plan's design (inline form pattern). No data flows through this stub.

## Requirements Covered

| Requirement | Handler/Template | Status |
|-------------|-----------------|--------|
| LIST-01 | list_create_handler.php + list_form.php | Covered |
| LIST-02 | columns_handler.php + columns_create_handler.php + columns.php | Covered |
| LIST-03 | list_column_create_handler.php | Covered |
| LIST-04 | lists_handler.php + lists.php | Covered |
| LIST-05 | list_settings_handler.php | Covered |

## Self-Check: PASSED

Files exist check:
- src/coach/lists_handler.php: FOUND
- src/coach/list_create_handler.php: FOUND
- src/coach/list_settings_handler.php: FOUND
- src/coach/columns_handler.php: FOUND
- src/coach/columns_create_handler.php: FOUND
- src/coach/list_column_create_handler.php: FOUND
- src/templates/coach/lists.php: FOUND
- src/templates/coach/list_form.php: FOUND
- src/templates/coach/columns.php: FOUND
- src/templates/coach/column_form.php: FOUND

Commits exist:
- 5e4a738: FOUND (Task 1)
- cf96992: FOUND (Task 2)
