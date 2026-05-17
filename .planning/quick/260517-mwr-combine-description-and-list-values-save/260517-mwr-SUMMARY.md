---
phase: quick
plan: 260517-mwr
subsystem: coordinator/list-detail
tags: [ux, forms, coordinator, list-detail]
dependency_graph:
  requires: []
  provides: [save_all-action]
  affects: [list-detail-coordinator]
tech_stack:
  added: []
  patterns: [single-form-save, show_full_form-flag]
key_files:
  created: []
  modified:
    - src/coordinator/list_detail_handler.php
    - src/templates/coordinator/list_detail.php
decisions:
  - "show_full_form flag controls whether description textarea lives in the main form or a standalone form"
  - "save_description branch kept intact for empty-state paths and backward compat"
  - "save_cells branch extended with explicit elseif to make save_all's dual-save intent clear"
metrics:
  duration: ~10 minutes
  completed: 2026-05-17
  tasks_completed: 2
  files_modified: 2
---

# Quick Task 260517-mwr: Combine Description and List Values Save - Summary

**One-liner:** Single `save_all` POST merges description textarea + cell values behind one "Speichern" button at the bottom of the list detail page.

## Objective

Reduced coordinator friction on the list detail page — previously two separate save buttons (one for description, one for cells); now a single form covering both.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Add save_all handler action | 9cb2fd1 | src/coordinator/list_detail_handler.php |
| 2 | Merge template into single form | 53f37c4 | src/templates/coordinator/list_detail.php |

## Changes Made

### Handler (list_detail_handler.php)

- Changed the fallthrough `else` (save_cells) to an explicit `elseif ($action === 'save_all' || $action === 'save_cells')` branch.
- When `$action === 'save_all'`, after cells are saved, an additional `UPDATE lists SET description = ?` runs atomically in the same try block.
- The existing `save_description` branch (lines 158-173) is untouched — still available for standalone description saves (empty-state forms).

### Template (list_detail.php)

- Added `$show_full_form` boolean computed from `$free_rows`/`$columns` (free list) or `$players`/`$columns` (member list).
- **When `$show_full_form` is true:** description textarea rendered inside the main form with `action=save_all`; no standalone description form shown.
- **When `$show_full_form` is false:** standalone description form with `action=save_description` shown above the empty-state message (existing behavior preserved).
- Both member list and free list main forms now carry `<input type="hidden" name="action" value="save_all">` and a description textarea at the top.
- Free-list delete-row ghost forms (`id="delete-row-{id}"`) are unaffected — they use their own `action=delete_row`.
- Single "Speichern" button at the bottom of the main form for both list types.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

### Files exist

- `src/coordinator/list_detail_handler.php` — modified
- `src/templates/coordinator/list_detail.php` — modified

### Commits exist

- `9cb2fd1` — feat(quick-260517-mwr): add save_all action to list detail handler
- `53f37c4` — feat(quick-260517-mwr): merge description and cells into single save form

## Self-Check: PASSED
