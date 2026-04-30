---
phase: 04-statistics-aggregation
plan: 03
subsystem: ui
tags: [php, bootstrap, statistics, player, aggregation, visibility-filter]

# Dependency graph
requires:
  - phase: 04-01
    provides: player layout with Statistik nav entry and /player/stats route
  - phase: 03-lists-columns-cells
    provides: EAV schema (columns, cells, lists tables), RLS visibility setup
provides:
  - GET /player/stats — visibility-filtered aggregation for current player only (STAT-01)
  - Player stats template showing single own-row table with global column headers
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Player stats query uses LEFT JOIN on cells with player_id filter and visibility LEFT JOIN to exclude private lists without subquery"
    - "WHERE (cells.id IS NULL OR lists.id IS NOT NULL) pattern excludes private-list cells while keeping players with no cells at all"
    - "Number formatting identical to coach stats template: integer if whole, 2 German-locale decimals otherwise"

key-files:
  created:
    - src/player/stats_handler.php
    - src/templates/player/stats.php
  modified: []

key-decisions:
  - "Visibility filter applied on LEFT JOIN condition (not WHERE) so players with zero cells still appear with 0 values (COALESCE guarantees this)"
  - "No player name column in table — player always views own row; label is redundant"
  - "table-bordered chosen over table-striped for single-row table; borders aid readability on mobile"
  - "No filter form on player stats — filtering is coach-only feature (STAT-02 scope)"

patterns-established:
  - "Player handler pattern: require_player() -> get_db() -> query -> require layout -> render_player_page('Title', 'stats', fn)"

requirements-completed:
  - STAT-01

# Metrics
duration: 2min
completed: 2026-04-30
---

# Phase 4 Plan 03: Player Statistics Page Summary

**Player statistics page at /player/stats showing current player's own aggregated values across public and protected lists, with visibility-filtered LEFT JOIN query and single-row Bootstrap table**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-04-30T12:45:41Z
- **Completed:** 2026-04-30T12:47:34Z
- **Tasks:** 2
- **Files created:** 2

## Accomplishments

- Created `src/player/stats_handler.php`: enforces player middleware via `require_player()`, fetches global columns for team, runs visibility-filtered aggregation query restricted to own `player_id`, passes results to `render_player_page()` with 'stats' active
- Created `src/templates/player/stats.php`: Bootstrap `table-responsive` single-row table with global columns as headers; empty state for no global columns; info text explaining visibility scope; number formatting matching coach stats (integer or 2 German-locale decimals); XSS-safe column names via `e()`

## Task Commits

Each task was committed atomically:

1. **Task 1: Player stats handler with visibility-filtered aggregation** - `c64520c` (feat)
2. **Task 2: Player stats template showing own row** - `42b89ec` (feat)

## Files Created/Modified

- `src/player/stats_handler.php` (created) — GET /player/stats handler; require_player() middleware; visibility-filtered aggregation query; render_player_page('Meine Statistik', 'stats', ...)
- `src/templates/player/stats.php` (created) — Bootstrap table-responsive single-row stats table; empty state; visibility info banner

## Decisions Made

- Visibility filter applied on LEFT JOIN condition (not in WHERE) so players with zero cell entries still appear with 0 values via COALESCE; the WHERE `(cells.id IS NULL OR lists.id IS NOT NULL)` correctly excludes cells from private lists while retaining no-cell cases
- No player name column in table — player views only own row; a name label would be redundant and add unnecessary width on mobile
- `table-bordered` (not `table-striped`) for the single-row table: with only one data row, borders improve readability more than row striping
- No filter form on player stats page — filtering by list or date range is a coach-only feature (STAT-02); player sees full aggregate across all public+protected lists

## Deviations from Plan

None - plan executed exactly as written.

## Known Stubs

None — all data is wired from the aggregation query to the template.

## Issues Encountered

None.

## Self-Check: PASSED

- src/player/stats_handler.php: FOUND
- src/templates/player/stats.php: FOUND
- c64520c: FOUND (feat(04-03): create player stats handler)
- 42b89ec: FOUND (feat(04-03): create player stats template)

---
*Phase: 04-statistics-aggregation*
*Completed: 2026-04-30*
