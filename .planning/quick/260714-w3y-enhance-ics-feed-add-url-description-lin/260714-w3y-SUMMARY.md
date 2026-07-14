---
phase: quick
plan: 260714-w3y
subsystem: calendar/ics
tags: [ics, calendar, migration, forms, list-view]
key-files:
  created: []
  modified:
    - src/db/connection.php
    - src/ics_handler.php
    - src/utils/calendar.php
    - src/coordinator/list_create_handler.php
    - src/templates/coordinator/list_form.php
    - src/coordinator/list_settings_handler.php
    - src/coordinator/lists_handler.php
    - src/member/lists_handler.php
    - src/templates/coordinator/lists.php
    - src/templates/member/lists.php
decisions:
  - "DB_HAS_LIST_TIMES guards all time_start/time_end usage — safe on installs that have not yet run migration 011"
  - "ICS SELECT branches on DB_HAS_LIST_TIMES to avoid querying non-existent columns"
  - "DTSTART emits floating local time (no Z, no TZID) when time_start set — RFC 5545 compliant for local-time events"
  - "DTEND defaults to time_start+1h when no time_end (avoids zero-duration events in calendar apps)"
  - "list_create_handler INSERT restructured to string concatenation to avoid combinatorial DB_HAS_LIST_TYPE x DB_HAS_LIST_TIMES branching"
  - "foldIcsLine() uses strlen (byte count) for ASCII-safe RFC 5545 line folding at 75 octets"
metrics:
  duration: "~15 minutes"
  completed: "2026-07-14"
  tasks: 3
  files: 10
---

# Quick Task 260714-w3y Summary

ICS feed enhanced with URL + DESCRIPTION per event, optional time_start/time_end on lists via Migration 011, datetime DTSTART/DTEND for timed events, and clock-icon time badges in list views.

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Migration 011 + ICS URL/DESCRIPTION/datetime | 04d7b8e |
| 2 | Beginn/Ende time inputs in create + settings forms | 12f680d |
| 3 | time_start in list view handlers + time badge in templates | 52f0d09 |

## What Was Built

### Migration 011 (src/db/connection.php)
Added `time_start TIME NULL` and `time_end TIME NULL` columns to the `lists` table via idempotent ALTER. Defines `DB_HAS_LIST_TIMES` constant (bool) so all downstream code can guard safely on installs that haven't run the migration yet.

### ICS Enhancements (src/ics_handler.php + src/utils/calendar.php)
- `foldIcsLine()` added to `calendar.php`: RFC 5545 §3.1 line folding at 75 octets with `\r\n ` continuation
- ICS SELECT branches on `DB_HAS_LIST_TIMES` — includes `time_start`, `time_end`, `description` when available
- **Timed events**: when `time_start` set, emits `DTSTART:20260714T180000` (floating local time, no VALUE=DATE)
- `DTEND` = `time_end` when set; otherwise `time_start + 1 hour` via `DateTime::modify('+1 hour')`
- **All-day fallback**: `DTSTART;VALUE=DATE:YYYYMMDD` preserved for lists without `time_start`
- `URL` property added to every VEVENT, linking to `/coordinator/lists/{id}`
- `DESCRIPTION` property added to every VEVENT: list description (if present) + URL, separated by `\n`

### Create Form (src/templates/coordinator/list_form.php + src/coordinator/list_create_handler.php)
- Form shows "Beginn" and "Ende" `<input type="time">` inputs after the date section, guarded by `DB_HAS_LIST_TIMES`
- Handler parses `HH:MM` input → stores as `HH:MM:SS` for PostgreSQL `TIME` type
- INSERT restructured from dual-branch (`DB_HAS_LIST_TYPE` if/else) to string-concatenation approach that independently appends `time_start, time_end` when `DB_HAS_LIST_TIMES` — eliminates combinatorial branching

### Settings Form (src/coordinator/list_settings_handler.php)
- SELECT uses `$time_cols` guard to append `, time_start, time_end` when `DB_HAS_LIST_TIMES`
- POST handler parses and validates time inputs (regex `^\d{2}:\d{2}$`)
- UPDATE query branches on `DB_HAS_LIST_TIMES`: includes `time_start = ?, time_end = ?` when available
- Settings form shows pre-filled Beginn/Ende time inputs (`substr(HH:MM:SS, 0, 5)` for the HTML time input)

### List View Time Badges (handlers + templates)
- Both `lists_handler.php` files: `$time_col = DB_HAS_LIST_TIMES ? 'time_start' : 'NULL AS time_start'` in SELECT
- Both `lists.php` templates: clock icon + `HH:MM` badge appears inline next to the calendar date icon in list-view cards when `$item['time_start']` is non-empty

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all data is wired to the database and rendered from real values.

## Self-Check: PASSED

Files exist:
- src/db/connection.php — FOUND (contains DB_HAS_LIST_TIMES define)
- src/utils/calendar.php — FOUND (contains foldIcsLine)
- src/ics_handler.php — FOUND (contains URL and DESCRIPTION properties)
- src/templates/coordinator/list_form.php — FOUND (contains time_start input)
- src/coordinator/list_create_handler.php — FOUND (contains DB_HAS_LIST_TIMES guard)
- src/coordinator/list_settings_handler.php — FOUND (contains time_start/time_end UPDATE)
- src/coordinator/lists_handler.php — FOUND (contains DB_HAS_LIST_TIMES time_col)
- src/member/lists_handler.php — FOUND (contains DB_HAS_LIST_TIMES time_col)
- src/templates/coordinator/lists.php — FOUND (contains bi-clock badge)
- src/templates/member/lists.php — FOUND (contains bi-clock badge)

Commits exist:
- 04d7b8e — FOUND (Task 1)
- 12f680d — FOUND (Task 2)
- 52f0d09 — FOUND (Task 3)
