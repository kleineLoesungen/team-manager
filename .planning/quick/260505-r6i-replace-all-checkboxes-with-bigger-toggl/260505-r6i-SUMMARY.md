---
phase: quick
plan: 260505-r6i
subsystem: frontend/ui
tags: [ui, forms, toggles, bootstrap, visual-consistency]
dependency_graph:
  requires: []
  provides: [consistent-toggle-style-across-all-forms]
  affects: [list_settings_handler, stats, list_form, list_row_form_coordinator, list_row_form_member]
tech_stack:
  added: []
  patterns: [Bootstrap form-switch, d-flex align-items-center gap-2 toggle wrapper]
key_files:
  created: []
  modified:
    - src/coordinator/list_settings_handler.php
    - src/templates/coordinator/stats.php
    - src/templates/coordinator/list_form.php
    - src/templates/coordinator/list_row_form.php
    - src/templates/member/list_row_form.php
decisions:
  - "Radio buttons for list_type in list_form.php left as plain form-check (not checkboxes; not in scope)"
  - "All toggle wrappers use d-flex align-items-center gap-2 instead of style=min-height for consistent layout"
metrics:
  duration: ~5 minutes
  completed: 2026-05-05
  tasks_completed: 3
  tasks_total: 3
  files_modified: 5
---

# Quick Task 260505-r6i: Replace All Checkboxes With Form-Switch Toggles — Summary

**One-liner:** Converted all plain Bootstrap checkboxes to form-switch toggles (width:3em; height:1.75em) with d-flex wrapper and mb-0 labels across 5 PHP templates.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Convert plain checkboxes in list_settings_handler.php and stats.php | 380028c | list_settings_handler.php, stats.php |
| 2 | Convert global_columns and defaults checkboxes in list_form.php; normalise show_all_rows size | 95da57c | list_form.php |
| 3 | Normalise boolean cell toggle size in coordinator and member list_row_form.php | 64b93cd | list_row_form.php (x2) |

## Changes Made

### list_settings_handler.php
- `show_all_rows` checkbox: plain `form-check` → `form-check form-switch d-flex align-items-center gap-2` with `role="switch"`, `style="width:3em;height:1.75em;cursor:pointer;"`, `mb-0` label
- `is_hidden` checkbox: same conversion

### stats.php
- `include_undated` checkbox: plain `form-check mb-0` → `form-check form-switch d-flex align-items-center gap-2 mb-0` with role, style, mb-0 label

### list_form.php
- `show_all_rows` wrapper: removed `style="min-height:1.75em;"`, added `d-flex align-items-center gap-2`; added `mb-0` to label
- `global_columns[]` per-column checkbox: plain `form-check` → full form-switch with role, correct size, `mb-0` label
- `defaults[col_id]` boolean sub-checkbox: `form-check form-check-sm` → form-switch with role, correct size, `mb-0 text-muted small` label

### list_row_form.php (coordinator and member)
- Boolean cell toggle wrapper: removed `style="min-height:2em;"`, added `d-flex align-items-center gap-2`
- Toggle input: resized from `width:3.5em;height:2em` to `width:3em;height:1.75em`
- Label: replaced `ms-1 align-self-center` with `mb-0`

## Deviations from Plan

None — plan executed exactly as written. Radio button inputs for list_type were correctly left untouched as specified in success criteria.

## Verification Results

- No plain `class="form-check"` wrapping checkbox inputs remains in any of the 5 touched files (two remaining plain form-check divs in list_form.php lines 17/22 are `type="radio"` inputs — correctly excluded per plan)
- All 8 toggle inputs across 5 files use `width:3em;height:1.75em;cursor:pointer;`
- No oversized `3.5em` or `height:2em` toggles remain in list_row_form files
- list_detail.php left untouched (already compliant)

## Self-Check: PASSED

Files confirmed modified:
- src/coordinator/list_settings_handler.php — FOUND
- src/templates/coordinator/stats.php — FOUND
- src/templates/coordinator/list_form.php — FOUND
- src/templates/coordinator/list_row_form.php — FOUND
- src/templates/member/list_row_form.php — FOUND

Commits confirmed:
- 380028c — FOUND
- 95da57c — FOUND
- 64b93cd — FOUND
