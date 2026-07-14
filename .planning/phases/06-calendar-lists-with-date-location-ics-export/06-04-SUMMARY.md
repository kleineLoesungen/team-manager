---
phase: 06-calendar-lists-with-date-location-ics-export
plan: "04"
subsystem: ui
tags: [php, bootstrap, calendar, ics, member]

requires:
  - phase: 06-01
    provides: location column on lists table, date field already existed
  - phase: 06-02
    provides: src/utils/calendar.php with getWeekBoundaries/getMonthBoundaries

provides:
  - Member /lists page with Kalender/Liste tab-switcher
  - Calendar timeline showing public+protected lists with location and day grouping
  - ICS subscription URL info box on member calendar
  - Undated items in "Ohne Datum" section
  - Preserved member card layout under Liste tab

affects:
  - member lists view
  - ICS subscription UX for members

tech-stack:
  added: []
  patterns:
    - Calendar view reuse: same getWeekBoundaries/getMonthBoundaries shared between coordinator and member handlers
    - Member-specific badge labels (Nur lesen vs Geschützt) maintained throughout calendar view
    - Visibility filter (public+protected only) enforced at SQL level, not PHP level

key-files:
  created: []
  modified:
    - src/member/lists_handler.php
    - src/templates/member/lists.php

key-decisions:
  - "Member calendar uses identical getWeekBoundaries/getMonthBoundaries from calendar.php as coordinator view — no duplication"
  - "Visibility filter kept in SQL (not PHP filter) — preserves existing security boundary"
  - "NULL AS location in files SELECT — uniform column set across merged $items array"

patterns-established:
  - "Badge labels are role-specific: member sees Nur lesen (protected), coordinator sees Geschützt"
  - "Detail URLs in member templates always /member/lists/ and /member/files/ — never coordinator URLs"

requirements-completed:
  - CAL-04

duration: 2min
completed: "2026-07-14"
---

# Phase 6 Plan 04: Member Calendar View Summary

**Member /lists page extended with Kalender tab showing day-grouped public+protected lists with location, ICS subscription URL, week/month navigation, and Ohne Datum section — Liste tab preserves existing card layout unchanged**

## Performance

- **Duration:** 2 min
- **Started:** 2026-07-14T20:31:37Z
- **Completed:** 2026-07-14T20:33:28Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Member lists handler now fetches `location` column and computes calendar boundaries using the shared `calendar.php` utility
- Member lists template rewritten with Kalender/Liste tab-switcher; calendar view shows ICS info box, week/month toggle, period navigation, day-grouped dated entries with location display, and Ohne Datum undated section
- Existing member card layout (Ältere Einträge collapse, /member/lists/ URLs, Nur lesen badge) fully preserved under Liste tab

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend member lists handler with calendar view logic** - `d8d4f05` (feat)
2. **Task 2: Rewrite member lists template with tab-switcher and calendar** - `3b2d160` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `src/member/lists_handler.php` - Added location to lists SELECT, NULL AS location to files SELECT, calendar view logic block, expanded use() list
- `src/templates/member/lists.php` - Full rewrite: Kalender/Liste tabs, ICS info box, calendar timeline, undated section, preserved card layout

## Decisions Made
- Used same `getWeekBoundaries`/`getMonthBoundaries` functions from `src/utils/calendar.php` (created in 06-02) — no duplication
- Kept visibility filter at SQL level (`visibility IN ('public', 'protected')`) — consistent with existing security model
- Added `NULL AS location` to files SELECT so `$items` array has uniform column set when arrays are merged

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 6 is now complete: all 4 plans executed (schema, coordinator handler/template, ICS endpoint, member handler/template)
- Members can view their calendar at /member/lists with full ICS subscription support
- No blockers

---
*Phase: 06-calendar-lists-with-date-location-ics-export*
*Completed: 2026-07-14*
