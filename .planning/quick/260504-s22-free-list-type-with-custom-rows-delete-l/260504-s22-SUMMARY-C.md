---
phase: 260504-s22
plan: C
subsystem: frontend/templates
tags: [toggle, boolean, mobile-ux, bootstrap]
dependency_graph:
  requires: []
  provides: [larger-boolean-toggles]
  affects: [coach-list-detail, coach-row-edit, player-row-edit]
tech_stack:
  added: []
  patterns: [Bootstrap form-switch with inline sizing]
key_files:
  modified:
    - src/templates/coach/list_detail.php
    - src/templates/coach/list_row_form.php
    - src/templates/player/list_row_form.php
decisions:
  - "Inline styles (no custom CSS class) keep the change self-contained and require no build step"
  - "3em/1.75em for table cells, 3.5em/2em for standalone form fields — slightly larger in forms for easier tapping"
metrics:
  duration: ~5 minutes
  completed: 2026-05-04
  tasks_completed: 1
  files_modified: 3
---

# Phase 260504-s22 Plan C: Larger Boolean Toggle Switches Summary

**One-liner:** Bootstrap form-switch enlarged via inline styles (3–3.5em wide, 1.75–2em tall) with cursor:pointer for mobile tap targets across all three list templates.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Larger boolean toggles in all three templates | d394148 | list_detail.php, coach/list_row_form.php, player/list_row_form.php |

## What Was Built

Three PHP templates were updated so that boolean (Ja/Nein) column cells render as larger Bootstrap toggle switches:

- **`src/templates/coach/list_detail.php`** — two boolean blocks updated: one in the free-list bulk-edit table (`$row` loop) and one in the member-list bulk-edit table (`$player` loop). Both now use `style="width:3em;height:1.75em;cursor:pointer;"` on the input and `style="min-height:1.75em;"` on the wrapper div.
- **`src/templates/coach/list_row_form.php`** — single-row coach edit form; boolean field upgraded from plain `form-check` to `form-check form-switch` with `width:3.5em;height:2em;cursor:pointer;`. Label gets `ms-1 align-self-center`.
- **`src/templates/player/list_row_form.php`** — identical treatment to the coach row form.

POST behavior (name, value attributes) is completely unchanged. Only CSS classes and inline styles were modified.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- `src/templates/coach/list_detail.php` — exists, contains `width:3em;height:1.75em` (both blocks)
- `src/templates/coach/list_row_form.php` — exists, contains `width:3.5em;height:2em`
- `src/templates/player/list_row_form.php` — exists, contains `width:3.5em;height:2em`
- Commit d394148 exists in git log
