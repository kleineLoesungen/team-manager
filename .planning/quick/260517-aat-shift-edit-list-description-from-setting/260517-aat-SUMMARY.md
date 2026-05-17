---
phase: quick-260517-aat
plan: 01
subsystem: coordinator/list-detail
tags: [ux, description, inline-edit, settings]
dependency_graph:
  requires: []
  provides: [inline-description-edit-on-list-detail]
  affects: [list_detail_handler, list_detail_template, list_settings_handler]
tech_stack:
  added: []
  patterns: [POST-redirect-GET, inline-form]
key_files:
  created: []
  modified:
    - src/coordinator/list_detail_handler.php
    - src/templates/coordinator/list_detail.php
    - src/coordinator/list_settings_handler.php
decisions:
  - "Inline description form always visible (not conditional on empty) — reduces friction for editing"
  - "Empty submission saves NULL to DB (consistent with other optional fields)"
metrics:
  duration: "~5 minutes"
  completed: "2026-05-17T05:27:21Z"
  tasks_completed: 2
  files_modified: 3
---

# Quick Task 260517-aat: Shift Edit List Description from Settings

**One-liner:** Moved inline description textarea to list detail view; settings UPDATE no longer touches description column.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add save_description POST handler to list_detail_handler.php | 157c3b6 | src/coordinator/list_detail_handler.php |
| 2 | Replace read-only description with inline form; remove from settings | 9294cd5 | src/templates/coordinator/list_detail.php, src/coordinator/list_settings_handler.php |

## Changes Summary

### Task 1 — list_detail_handler.php

Added `elseif ($action === 'save_description')` branch before the `save_cells` else block. The handler:
- Extracts and trims `$_POST['description']`
- Updates `lists.description` (NULL if empty) and `updated_at` for the list
- Ownership enforced via `team_id` in the WHERE clause
- Redirects to list detail with `?success=1` on success

### Task 2 — list_detail.php + list_settings_handler.php

**list_detail.php:** Replaced the conditional `<p class="text-muted small mb-3">` paragraph with an always-visible `<form>` containing a textarea and Speichern button, posting `action=save_description` to the list detail handler.

**list_settings_handler.php:**
- Removed the description textarea block (label + textarea for `list_desc`)
- Removed `$new_description` variable extraction from POST data
- Removed `description = ?,` from the UPDATE query and its execute parameter
- The SELECT at file top still fetches `description` (harmless, left as-is per plan)

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

All 3 modified files exist on disk. Both task commits (157c3b6, 9294cd5) confirmed in git log.
