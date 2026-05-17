---
phase: quick
plan: 260517-amy
subsystem: coordinator/lists
tags: [list-settings, rename, form, validation]
key-files:
  modified:
    - src/coordinator/list_settings_handler.php
decisions:
  - Keep name editing in the same form/handler block as visibility and date — no separate action branch
metrics:
  duration: "< 5 minutes"
  completed: "2026-05-17"
  tasks: 1
  files: 1
---

# Quick 260517-amy: List Settings Name Field Summary

**One-liner:** Adds a pre-filled name text input as the first field in the list settings form with German validation (required + max 100 chars) and persists via `UPDATE lists SET name = ?`.

## What Was Done

Added a `name` field to the coordinator list settings page (`/coordinator/lists/{id}/settings`) so coordinators can rename a list without creating a new one.

**Form template change:** A `<input type="text" name="name">` field pre-filled with the current list name was inserted as the first field inside the existing settings `<form>` block, above the visibility select.

**POST handler change:** The `else` branch (settings save path) now reads and validates `$new_name`:
- Empty name → German error: `Name ist erforderlich.`
- Name longer than 100 chars → German error: `Name darf max. 100 Zeichen haben.`
- `name = ?` added to the existing `UPDATE lists SET ...` statement as the first bind parameter
- Ownership check (`WHERE id = ? AND team_id = ?`) unchanged

## Tasks

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Add name field to list settings form and handler | 1f97590 | src/coordinator/list_settings_handler.php |

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- `src/coordinator/list_settings_handler.php` — exists and modified
- Commit `1f97590` — verified in git log
- PHP syntax check passed: `No syntax errors detected`
