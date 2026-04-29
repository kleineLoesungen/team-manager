---
phase: 02-team-player-mgmt
plan: "01"
subsystem: auth
tags: [php, session, middleware, bootstrap5, routing]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: session.php with require_admin(), layout.php with render_layout_head/foot/navbar, public/index.php router pattern
provides:
  - require_coach() middleware enforcing role='coach' and RLS team context
  - render_coach_page() callable-body layout for coach area
  - /coach/* router entries (players list, create, action)
  - Role-based login redirect (coach → /coach/players, player → /player)
affects:
  - 02-02-players-list
  - 02-03-player-create-reset
  - 03-lists-columns
  - 04-statistics

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "require_coach() mirrors require_admin() — check role, set RLS context, redirect on failure"
    - "render_coach_page() mirrors render_admin_page() — callable $body for co-located template logic"
    - "Router match blocks in public/index.php: exact paths first, then regex closures for parameterised routes"

key-files:
  created:
    - src/templates/coach/layout.php
  modified:
    - src/auth/session.php
    - src/auth/login_handler.php
    - public/index.php

key-decisions:
  - "Separate coach layout from admin layout (D-01) — no cross-role template sharing"
  - "Phase 2 coach nav has only 'Spieler' — no placeholder items for future nav"
  - "Already-authenticated coach redirect also updated (not just post-login redirect)"

patterns-established:
  - "require_coach(): check role from session, set_team_context(get_db(), team_id), redirect /login on fail"
  - "render_coach_page(title, active, callable): same callable-body pattern as render_admin_page()"

requirements-completed: [TEAM-04, AUTH-03]

# Metrics
duration: 5min
completed: 2026-04-29
---

# Phase 2 Plan 01: Coach Area Foundation Summary

**require_coach() middleware with RLS team context, render_coach_page() Bootstrap 5 sidebar layout, and /coach/* router stubs — scaffolding for all Phase 2 plans**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-04-29T19:28:00Z
- **Completed:** 2026-04-29T19:33:00Z
- **Tasks:** 2
- **Files modified:** 4 (2 modified, 1 created, 1 modified)

## Accomplishments
- require_coach() added to session.php: enforces role='coach', sets RLS team context via set_team_context(), redirects to /login on failure
- Role-based redirects in login_handler.php: coaches go to /coach/players, players go to /player (placeholder)
- render_coach_page() created in src/templates/coach/layout.php: Bootstrap 5 two-column layout (sidebar desktop, tab mobile) with single "Spieler" nav item
- Router entries for /coach, /coach/players, /coach/players/create, and /coach/players/{id}/{action} added to public/index.php

## Task Commits

Each task was committed atomically:

1. **Task 1: Add require_coach() to session.php and update login redirect** - `e2b4e50` (feat)
2. **Task 2: Create coach layout template and register /coach/* router entries** - `7f5f31e` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified
- `src/auth/session.php` - Added require_coach() after require_admin()
- `src/auth/login_handler.php` - Role-based redirects: coach → /coach/players, player → /player (both post-login and already-authenticated)
- `src/templates/coach/layout.php` - New file: render_coach_page() callable-body layout
- `public/index.php` - Added /coach/* route block before 404 default

## Decisions Made
- Separate coach layout from admin layout (no cross-role template sharing) — matches plan D-01
- Coach nav in Phase 2 has only "Spieler" — no placeholder items for routes not yet implemented
- Also updated the already-authenticated redirect check at the top of login_handler.php (not just post-login) so coaches visiting /login while already logged in go to /coach/players too

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Coach scaffolding complete; Plans 02 and 03 can now call require_coach() and render_coach_page()
- Handler files src/coach/players_handler.php, src/coach/player_create_handler.php, src/coach/player_action_handler.php are referenced by the router but not yet created — they will be created in Plans 02 and 03

---
*Phase: 02-team-player-mgmt*
*Completed: 2026-04-29*
