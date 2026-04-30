---
phase: 04-statistics-aggregation
plan: 02
subsystem: ui
tags: [php, postgresql, statistics, aggregation, bootstrap, leaderboard]

# Dependency graph
requires:
  - phase: 04-statistics-aggregation
    plan: 01
    provides: /coach/stats route registered in front controller and Statistik nav item in coach layout
  - phase: 03-lists-columns-cells
    provides: EAV schema (columns, cells, list_global_columns tables), require_coach(), render_coach_page()
provides:
  - GET /coach/stats — aggregated player statistics table with filter form and leaderboard section
  - src/coach/stats_handler.php — aggregation queries, filter params, leaderboard query, template render
  - src/templates/coach/stats.php — Bootstrap statistics table, filter form, leaderboard with dropdown
affects:
  - 04-03-PLAN (player stats handler — same patterns apply with visibility filter for player context)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CROSS JOIN on global columns subquery ensures every player appears for every column even with no cells"
    - "COALESCE(SUM/COUNT, 0) converts NULL aggregates to 0 — players without entries show 0, not blank"
    - "sort_order included in CROSS JOIN subquery SELECT and GROUP BY to allow ORDER BY in outer query"
    - "Leaderboard data_type passed twice as positional PDO params for CASE condition"
    - "date filter applied to cells.updated_at per RESEARCH.md open question 1 recommendation"
    - "onchange=this.form.submit() on leaderboard dropdown — progressive enhancement with noscript fallback"

key-files:
  created:
    - src/coach/stats_handler.php
    - src/templates/coach/stats.php
  modified: []

key-decisions:
  - "CROSS JOIN pattern chosen over LEFT JOIN on list_global_columns to guarantee all-player × all-column matrix regardless of cell existence"
  - "Filter includes OR cells.list_id IS NULL for LEFT JOIN null rows — preserves players with no entries in filtered view"
  - "Number display: integer when floor(n)==n, else number_format(n, 2, ',', '.') — avoids unnecessary decimal places for whole numbers"
  - "Leaderboard respects same filter state (list_id, date_from, date_to) as statistics table — hidden inputs carry filter state between forms"
  - "sort_order added to CROSS JOIN subquery columns (deviation from plan) — required for valid GROUP BY referencing c.sort_order in ORDER BY"

# Metrics
duration: ~6min
completed: 2026-04-30
---

# Phase 4 Plan 02: Coach Statistics Page Summary

**Coach statistics page at /coach/stats: aggregated player metrics table with list/date filters and leaderboard, using PostgreSQL CROSS JOIN + COALESCE to guarantee all players appear with 0 for empty entries**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-04-30T12:37:50Z
- **Completed:** 2026-04-30T12:43:28Z
- **Tasks:** 2
- **Files created:** 2

## Accomplishments

- `src/coach/stats_handler.php`: Full aggregation handler — fetches global columns, runs CROSS JOIN query for all-player × all-column matrix, parses GET filter params (list_id, date_from, date_to), runs leaderboard query, renders template via `render_coach_page('Statistik', 'stats', ...)`
- `src/templates/coach/stats.php`: Three-section template — filter form (list dropdown + date range + reset), statistics table (`table-responsive`, one row per player, integer/decimal number formatting), leaderboard table with sort_by dropdown (onchange auto-submit with noscript fallback)
- Both files pass PHP lint with no syntax errors

## Task Commits

Each task was committed atomically:

1. **Task 1: Create coach stats handler with aggregation and leaderboard queries** — `50bc439` (feat)
2. **Task 2: Create coach stats template with statistics table, filter form, and leaderboard** — `45a8463` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `src/coach/stats_handler.php` — CREATED: aggregation query, leaderboard query, filter params, render call
- `src/templates/coach/stats.php` — CREATED: filter form, statistics table, leaderboard section

## Decisions Made

- CROSS JOIN on global columns subquery guarantees all players appear for all columns even with no cell data
- `sort_order` included in CROSS JOIN subquery SELECT and GROUP BY — required for valid ORDER BY in outer query (auto-fix from plan omission)
- Filter preserves players with no matching cells via `OR cells.list_id IS NULL` on LEFT JOIN null rows
- Leaderboard carries filter state (list_id, date_from, date_to) via hidden inputs so both forms work consistently
- Number formatting: whole numbers show as integer, fractional as 2 decimals with German locale (comma separator)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Added sort_order to CROSS JOIN subquery SELECT and GROUP BY**
- **Found during:** Task 1 implementation
- **Issue:** Plan's CROSS JOIN subquery only selected `id, name, data_type`. The `ORDER BY c.sort_order` in the outer GROUP BY required `sort_order` to be part of the subquery's columns and included in GROUP BY. Without it, the query would fail with a "column c.sort_order must appear in GROUP BY clause" PostgreSQL error.
- **Fix:** Added `sort_order` to the CROSS JOIN subquery `SELECT` and to the outer `GROUP BY` clause.
- **Files modified:** `src/coach/stats_handler.php`
- **Commit:** `50bc439`

## Issues Encountered

None beyond the sort_order auto-fix above.

## User Setup Required

None — no external service configuration required.

## Known Stubs

None — handler and template are fully wired. Statistics aggregate real cell data from the database; empty state (no global columns, no active players) shows appropriate German-language alerts.

## Next Phase Readiness

- Coach statistics page complete — Plan 04-03 can add player stats handler using the same patterns with visibility filter (public/protected only, per D-05)
- Template pattern established for both statistics table and leaderboard sections

---

*Phase: 04-statistics-aggregation*
*Completed: 2026-04-30*
