---
phase: 06-calendar-lists-with-date-location-ics-export
plan: 01
subsystem: database
tags: [postgresql, php, location, lists, schema]

# Dependency graph
requires:
  - phase: 05-email-notifications
    provides: users table with email column; lists table with date and description columns
provides:
  - location VARCHAR(255) NULL column on lists table (schema + migration comment)
  - Ort input field in coordinator list create form
  - location read/write in coordinator list settings form and handler
affects: [06-02, 06-03, 06-04]

# Tech tracking
tech-stack:
  added: []
  patterns: [same optional-field pattern as date — trim, validate length, NULL-when-empty in INSERT/UPDATE]

key-files:
  created: []
  modified:
    - database/schema.sql
    - src/coordinator/list_create_handler.php
    - src/templates/coordinator/list_form.php
    - src/coordinator/list_settings_handler.php

key-decisions:
  - "location stored as NULL when field is empty, consistent with existing date/description handling"
  - "mb_strlen/mb_substr used for 255-char truncation to handle multibyte characters correctly"

patterns-established:
  - "Optional text field pattern: trim → mb_strlen guard → NULL-when-empty binding — mirrors date/description"

requirements-completed: [CAL-01]

# Metrics
duration: 1min
completed: 2026-07-14
---

# Phase 6 Plan 01: Location Field — Schema, Create Form, and Settings Summary

**location VARCHAR(255) NULL added to lists table with idempotent schema, coordinator create and settings forms wired end-to-end**

## Performance

- **Duration:** 1 min
- **Started:** 2026-07-14T20:01:07Z
- **Completed:** 2026-07-14T20:02:40Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Added `location VARCHAR(255) NULL` to `team_manager.lists` in schema.sql with migration comment for existing databases
- Wired location into both INSERT branches of list_create_handler.php (DB_HAS_LIST_TYPE and else) with mb_strlen truncation guard
- Added Ort input (after Datum, before Beschreibung) to list_form.php with maxlength="255"
- Extended list_settings_handler.php: SELECT includes location, POST parses and validates new_location, UPDATE stores it, rendered form shows current value

## Task Commits

Each task was committed atomically:

1. **Task 1: Schema column + location field in create form and handler** - `25eec4d` (feat)
2. **Task 2: Location field in list settings handler and form** - `7996935` (feat)

**Plan metadata:** committed with docs commit

## Files Created/Modified
- `database/schema.sql` - Added `location VARCHAR(255) NULL` column after `date DATE NULL` in lists CREATE TABLE; added ALTER TABLE migration comment
- `src/coordinator/list_create_handler.php` - Added `$location` parsing and included location in both INSERT branches
- `src/templates/coordinator/list_form.php` - Added Ort input block between Datum and Beschreibung sections
- `src/coordinator/list_settings_handler.php` - Extended SELECT, added `$new_location` parsing, extended UPDATE, added Ort input in rendered form

## Decisions Made
- location stored as NULL when the field is empty — consistent with the existing date and description pattern already in the codebase
- mb_strlen/mb_substr used for the 255-char truncation guard to correctly handle multibyte characters

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

The live database needs the migration applied:
```sql
ALTER TABLE lists ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL;
```
This is safe to run on the production PostgreSQL instance — it is a no-op if the column already exists.

## Next Phase Readiness
- location column and data layer are fully wired; plans 06-02 through 06-04 can reference `$list['location']` in templates and ICS export without any additional backend changes
- No blockers

---
*Phase: 06-calendar-lists-with-date-location-ics-export*
*Completed: 2026-07-14*
