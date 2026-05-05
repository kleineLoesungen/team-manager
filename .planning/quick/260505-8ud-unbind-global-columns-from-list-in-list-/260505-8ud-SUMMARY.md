---
phase: quick
plan: 260505-8ud
subsystem: coach/list-settings
tags: [global-columns, unbind, two-step-confirm, no-js, pdo-transaction]
dependency_graph:
  requires: [list_global_columns junction table, cells table, columns table]
  provides: [unbind_column POST action, Globale Spalten card in list settings]
  affects: [src/coach/list_settings_handler.php]
tech_stack:
  added: []
  patterns: [two-step no-JS confirm, PDO transaction with rollback, ownership check via JOIN]
key_files:
  modified:
    - src/coach/list_settings_handler.php
decisions:
  - Delete cells before junction row within transaction to satisfy FK ordering
  - Ownership check joins list_global_columns + columns on team_id (not just list_id) to prevent cross-team attacks
  - Card only renders when global_columns is non-empty — no empty state clutter
metrics:
  duration: "~5 minutes"
  completed: "2026-05-05"
  tasks_completed: 1
  files_modified: 1
---

# Quick Task 260505-8ud: Unbind Global Columns from List in List Settings Summary

**One-liner:** Added "Globale Spalten" card to list settings with per-row two-step no-JS "Entfernen" confirmation that removes the junction row and cells for that list only, leaving the column record intact.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add global columns fetch and unbind_column POST handler | 19eba61 | src/coach/list_settings_handler.php |

## What Was Built

Added four coordinated changes to `src/coach/list_settings_handler.php`:

1. **Global columns fetch** — Query `list_global_columns` junction table joined to `columns` to get all active global columns attached to the current list. Runs before any POST processing so $global_columns is always available for rendering.

2. **`$unbind_pending_col_id = null`** — Mirrors `$delete_pending_col_id`; set to the column_id when confirm=0 is POSTed so the template knows which row to expand into confirmation state.

3. **`unbind_column` POST branch** — Added as `elseif` between the `delete_column` branch and the settings-update `else`:
   - `confirm !== 1`: sets `$unbind_pending_col_id` and falls through to render (shows inline confirmation)
   - `confirm === 1`: verifies ownership (JOIN checks both `list_global_columns.list_id` and `columns.team_id`), then runs a PDO transaction that first deletes cells for this list+column, then deletes the junction row. Column record in `columns` table is untouched.

4. **Globale Spalten template card** — Renders between Lokale Spalten and Gefahrenzone cards when `$global_columns` is non-empty. Uses identical two-step no-JS confirm pattern to the Lokale Spalten card.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- src/coach/list_settings_handler.php: modified and committed as 19eba61
- php -l reports no syntax errors
- All done criteria verified by code review of final file
