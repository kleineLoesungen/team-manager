---
phase: 260504-s22
plan: B
subsystem: coach/list-settings
tags: [columns, delete, list-settings, two-step-confirm, no-js]
key-files:
  modified:
    - src/coach/list_settings_handler.php
decisions:
  - "Reuse existing /moderator/lists/{id}/settings POST route for column delete — no new route needed"
  - "Two-step confirm via hidden input (confirm=0 → show inline message, confirm=1 → execute) avoids JS dependency"
  - "Triple ownership check (id + list_id + team_id) on DELETE as defense-in-depth against cross-list access"
metrics:
  duration: "~10 min"
  completed: "2026-05-04"
  tasks_completed: 1
  files_modified: 1
---

# Quick Task 260504-s22 Plan B Summary

**One-liner:** Local column delete in list settings with no-JS two-step confirmation and triple ownership check.

## What Was Built

Moderators can now delete local (list-specific) columns directly from the list settings page without JavaScript.

### Implementation Details

**`src/coach/list_settings_handler.php`**

- Added a query at the top (after `$list` is fetched) that loads all active local columns for the list (`list_id = ? AND team_id = ? AND is_active = TRUE`).
- Added `$delete_pending_col_id = null;` initialization so the variable is always defined when the closure runs.
- Added a new POST branch guarded by `$_POST['action'] === 'delete_column'` before the existing visibility/settings branch. First POST (confirm=0) stores the column ID in `$delete_pending_col_id` and falls through to render. Second POST (confirm=1) executes `DELETE FROM columns WHERE id = ? AND list_id = ? AND team_id = ?` then redirects. Cells cascade-delete via the existing FK (`cells.column_id REFERENCES columns ON DELETE CASCADE`).
- Expanded `render_coach_page` closure to `use ($list, $error, $local_columns, $delete_pending_col_id)`.
- Added a new Bootstrap card section between the main settings form and the Gefahrenzone block. The section is only rendered when `$local_columns` is non-empty. Each row shows column name + type badge plus either the initial Löschen button or the inline confirmation ("Spalte und alle Einträge löschen?") with Ja/Abbrechen.

## Verification Steps

1. Open list settings for a list with at least one local column — card "Lokale Spalten" appears.
2. Click Löschen — page reloads showing inline confirmation for that row only; other rows unchanged.
3. Click Abbrechen — returns to normal settings page; column still present.
4. Click Löschen again, then "Ja, löschen" — redirects to settings with `?success=1`; column no longer listed.
5. DB check: `SELECT id FROM columns WHERE id={col_id}` returns nothing.
6. DB check: `SELECT id FROM cells WHERE column_id={col_id}` returns nothing (cascade delete).
7. Global columns page (`/moderator/columns`) and global column management unaffected.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check

- [x] `src/coach/list_settings_handler.php` modified
- [x] Commit `0fa15b1` exists

## Self-Check: PASSED
