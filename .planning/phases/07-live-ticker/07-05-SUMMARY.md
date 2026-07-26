---
phase: 07-live-ticker
plan: 05
subsystem: ui
tags: [php, routing, navigation, public-endpoint, ticker]

# Dependency graph
requires:
  - phase: 07-02
    provides: coordinator ticker handlers (ticker_handler, ticker_create, ticker_detail, ticker_close, ticker_delete, settings_handler)
  - phase: 07-03
    provides: coordinator ticker templates + settings template (columns renamed to settings)
  - phase: 07-04
    provides: member + public ticker handlers and templates

provides:
  - All Phase 7 routes wired in public/index.php front controller
  - /coordinator/ticker, /coordinator/ticker/new, /{id}, /{id}/close, /{id}/delete routes
  - /coordinator/settings route (replaces /coordinator/columns)
  - 301 redirect from /coordinator/columns to /coordinator/settings (backward compat)
  - /coordinator/settings/columns/create + /coordinator/columns/create alias
  - /member/ticker and /member/ticker/{id} routes
  - /ticker and /ticker/{id} public routes (no auth)
  - Coordinator nav: Ticker (bi-megaphone) + Einstellungen (bi-gear, was Spalten)
  - Member nav: Ticker (bi-megaphone) after Inhalte
  - Login page: link to /ticker public overview (TICKER-06)

affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PHP match() route ordering: specific /close, /delete routes placed before generic /{id} pattern"
    - "301 redirect for URL rename backward compat (columns → settings)"
    - "URL alias pattern: new canonical URL + old URL both point to same handler"

key-files:
  created: []
  modified:
    - public/index.php
    - src/templates/coordinator/layout.php
    - src/templates/member/layout.php
    - src/templates/login.php
    - src/coordinator/settings_handler.php

key-decisions:
  - "Specific /close and /delete routes placed BEFORE generic /coordinator/ticker/{id} in match() — PHP match() evaluates in order, first truthy case wins"
  - "301 permanent redirect from /coordinator/columns to /coordinator/settings for backward compatibility"
  - "/coordinator/columns/create alias preserved alongside new /coordinator/settings/columns/create URL"
  - "settings_handler.php active param updated from 'columns' to 'settings' to match renamed nav item"

patterns-established:
  - "URL rename pattern: add new canonical route + 301 redirect from old URL + keep sub-path aliases"

requirements-completed: [TICKER-06]

# Metrics
duration: 2min
completed: 2026-07-26
---

# Phase 07 Plan 05: Routing + Navigation Wiring Summary

**9 new routes wired in index.php, coordinator nav gains Ticker + renames Spalten to Einstellungen, member nav gains Ticker, login page links to public /ticker (TICKER-06)**

## Performance

- **Duration:** 2 min
- **Started:** 2026-07-26T14:58:14Z
- **Completed:** 2026-07-26T15:00:26Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- All Phase 7 handler files from plans 07-02/03/04 are now accessible via URL
- /coordinator/columns permanently redirects to /coordinator/settings (backward compat 301)
- Coordinator navigation updated: Ticker entry added (bi-megaphone), Spalten renamed to Einstellungen (bi-gear), in both sidebar and mobile tab bar
- Member navigation updated: Ticker entry added after Inhalte in both sidebar and mobile tab bar
- Login page gained a public ticker link section below the card (TICKER-06 requirement)

## Task Commits

1. **Task 1: Wire all Phase 7 routes in public/index.php** - `88c017a` (feat)
2. **Task 2: Update coordinator + member nav layouts and login page** - `c5c07b5` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `public/index.php` - 9 new routes added: coordinator ticker (5), member ticker (2), public ticker (2); settings route + 301 redirect
- `src/templates/coordinator/layout.php` - Ticker nav entry added; Spalten renamed to Einstellungen in sidebar + mobile tabs
- `src/templates/member/layout.php` - Ticker nav entry added after Inhalte in sidebar + mobile tabs
- `src/templates/login.php` - Public ticker link section added below card (TICKER-06)
- `src/coordinator/settings_handler.php` - Active nav param updated from 'columns' to 'settings'

## Decisions Made
- Specific `/coordinator/ticker/{id}/close` and `/coordinator/ticker/{id}/delete` routes are placed BEFORE the generic `/coordinator/ticker/{id}` route in the match() expression — PHP match() short-circuits on first truthy case, so ordering is critical
- `/coordinator/columns` gets a 301 (permanent) redirect to `/coordinator/settings`; the sub-path `/coordinator/columns/create` is kept as an alias (no redirect) because it receives POST requests from forms that may not yet use the new URL
- `settings_handler.php` active param changed from `'columns'` to `'settings'` so the Einstellungen nav item highlights correctly

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness
- Phase 7 Live-Ticker feature is fully complete: schema, coordinator handlers, templates, member handlers, public handlers, routing, and navigation all wired
- The 'Spalten' nav entry references in old code comments (not functional) may be cleaned up in a future quick task
- All public endpoints (/ticker, /ticker/{id}) accessible without authentication as required by TICKER-06

---
*Phase: 07-live-ticker*
*Completed: 2026-07-26*
