---
phase: 04-statistics-aggregation
plan: 01
subsystem: ui
tags: [php, bootstrap, navigation, routing]

# Dependency graph
requires:
  - phase: 03-lists-columns-cells
    provides: coach and player layout templates with established nav patterns
provides:
  - Statistik nav entry in coach sidebar and mobile tabs linking to /coach/stats
  - Statistik nav entry in player sidebar and mobile tabs linking to /player/stats
  - /coach/stats route dispatching to src/coach/stats_handler.php
  - /player/stats route dispatching to src/player/stats_handler.php
affects:
  - 04-02-PLAN (coach stats handler — navigation already in place)
  - 04-03-PLAN (player stats handler — navigation already in place)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Stats nav item uses bi-graph-up Bootstrap Icon — consistent with statistics semantics"
    - "active === 'stats' condition follows existing active === 'lists' / active === 'columns' pattern"

key-files:
  created: []
  modified:
    - src/templates/coach/layout.php
    - src/templates/player/layout.php
    - public/index.php

key-decisions:
  - "Route entries added without requiring handler files to exist — PHP only requires on route match; Plans 02 and 03 create handlers"
  - "bi-graph-up icon chosen for Statistik nav item — consistent with statistics semantics, already available via Bootstrap Icons CDN"

patterns-established:
  - "New nav items follow established pattern: sidebar li.nav-item + mobile tab anchor, both checking $active === 'key'"

requirements-completed:
  - STAT-01
  - STAT-02
  - STAT-03

# Metrics
duration: 5min
completed: 2026-04-30
---

# Phase 4 Plan 01: Statistics Navigation & Routing Summary

**Statistik nav entries wired into coach and player layouts (sidebar + mobile tabs), and /coach/stats and /player/stats routes registered in the front controller pointing to their respective handler stubs**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-04-30T12:34:00Z
- **Completed:** 2026-04-30T12:36:04Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Coach layout: Statistik nav item added to sidebar (with bi-graph-up icon) and mobile tabs, with correct active state via $active === 'stats'
- Player layout: Statistik nav item added to sidebar (with bi-graph-up icon) and mobile tabs, with correct active state via $active === 'stats'
- Front controller: /coach/stats and /player/stats routes registered, dispatching to src/coach/stats_handler.php and src/player/stats_handler.php respectively

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Statistik nav item to coach and player layouts** - `f94c8ef` (feat)
2. **Task 2: Register /coach/stats and /player/stats routes in front controller** - `74f82cc` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `src/templates/coach/layout.php` - Added Statistik nav item to sidebar and mobile tabs; updated docblock for 'stats' active value
- `src/templates/player/layout.php` - Added Statistik nav item to sidebar and mobile tabs; updated docblock for 'stats' active value
- `public/index.php` - Added /coach/stats and /player/stats route entries in match block

## Decisions Made
- Route entries added without requiring handler files to exist — PHP only requires files on route match; Plans 02 and 03 will create the actual handlers
- bi-graph-up icon chosen for Statistik nav item — consistent with statistics semantics, already available via Bootstrap Icons CDN

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Navigation plumbing complete — Plans 02 and 03 can add coach and player stats handlers without touching layout or router
- /coach/stats and /player/stats will return a fatal require error until Plans 02/03 create the handler files (expected and intentional)

---
*Phase: 04-statistics-aggregation*
*Completed: 2026-04-30*
