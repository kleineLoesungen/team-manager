---
phase: 08-player-club-management
plan: "03"
subsystem: ui
tags: [php, admin, crud, clubs, bootstrap]

requires:
  - phase: 08-01
    provides: clubs table in database with id, name, is_active, created_at columns

provides:
  - Admin CRUD for clubs: list, create, edit name, deactivate, reactivate
  - clubs_handler.php: GET list grouped active/inactive, alphabetical within each group
  - club_create_handler.php: GET shows form, POST inserts with validation and CSRF
  - club_action_handler.php: POST edit/deactivate/reactivate with CSRF
  - clubs.php template: card list with inline edit forms and deactivate/reactivate buttons
  - club_form.php template: create club form
  - Admin nav (sidebar + mobile) updated with Klubs, Spieler, Attribut-Gruppen items
  - Routes in index.php: /admin/clubs, /admin/clubs/create, /admin/clubs/{id}/{action}

affects: [08-04, 08-05, 08-06, 08-07]

tech-stack:
  added: []
  patterns:
    - "GET+POST handler pattern: club_create_handler handles both display and submission unlike team_create_handler (POST-only)"
    - "Inline edit forms in list templates instead of Bootstrap modals — simpler for low-risk edits"

key-files:
  created:
    - src/admin/clubs_handler.php
    - src/admin/club_create_handler.php
    - src/admin/club_action_handler.php
    - src/templates/admin/clubs.php
    - src/templates/admin/club_form.php
  modified:
    - public/index.php
    - src/templates/admin/layout.php

key-decisions:
  - "Inline edit form (not modal) for club name — clubs are simpler than teams, no sub-entity display needed"
  - "club_create_handler uses GET+POST (shows form on GET) unlike team_create_handler which POST-redirects only"
  - "Navbar adds Spieler and Attribut-Gruppen links even though those pages are in later plans — enables discovery and navigation consistency from day one"

patterns-established:
  - "GET+POST handler that renders on GET and redirects on POST: render layout after validation block"
  - "e() for all user-controlled string output in templates; int cast for IDs in href attributes"

requirements-completed: [CLUB-01]

duration: 22min
completed: "2026-08-01"
---

# Phase 8 Plan 3: Clubs Admin CRUD Summary

**Admin CRUD for clubs with inline edit/deactivate/reactivate forms, separate create page, and Klubs/Spieler/Attribut-Gruppen links added to admin sidebar and mobile tab bar**

## Performance

- **Duration:** 22 min
- **Started:** 2026-08-01T10:25:59Z
- **Completed:** 2026-08-01T10:47:52Z
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments
- Admin can view all clubs at /admin/clubs grouped active-first, then inactive (collapsible)
- Admin can create clubs at /admin/clubs/create with name validation and CSRF
- Admin can edit club name, deactivate, or reactivate via inline forms with CSRF on each card
- Admin sidebar and mobile tab bar now show Klubs, Spieler, and Attribut-Gruppen nav links with correct active state

## Task Commits

Each task was committed atomically:

1. **Task 1: clubs_handler.php + club_create_handler.php** - `5480d73` (feat)
2. **Task 2: club_action_handler.php + templates + routes** - `e54c6b1` (feat)
3. **Task 3: Admin nav — add Klubs, Spieler, Attribut-Gruppen** - `f509d2c` (feat)

**Plan metadata:** _(docs commit to follow)_

## Files Created/Modified
- `src/admin/clubs_handler.php` - Lists clubs from DB ordered active-first, passes to clubs.php
- `src/admin/club_create_handler.php` - GET renders create form; POST validates and INSERTs
- `src/admin/club_action_handler.php` - POST-only: edit name, deactivate, or reactivate
- `src/templates/admin/clubs.php` - Club list UI with inline edit/deactivate cards, inactive collapse
- `src/templates/admin/club_form.php` - Simple create-club form with error display
- `public/index.php` - Added /admin/clubs, /admin/clubs/create, /admin/clubs/{id}/{action} routes
- `src/templates/admin/layout.php` - Added Klubs, Spieler, Attribut-Gruppen to sidebar and mobile tab bar

## Decisions Made
- Inline edit form (not modal) for club name — clubs are simpler entities than teams, no sub-entity list to display alongside
- `club_create_handler` renders on GET (shows form) unlike `team_create_handler` which only handles POST; matches the plan spec for a dedicated create page
- Added Spieler and Attribut-Gruppen nav links even though those admin sections don't exist yet — allows navigation discovery and sets up active states for plans 04-07

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Added error display passthrough to clubs_handler**
- **Found during:** Task 1 implementation review
- **Issue:** Plan's clubs_handler snippet showed no ?error= passthrough, but clubs.php template needs it for action errors
- **Fix:** Added `$error = !empty($_GET['error']) ? e($_GET['error']) : '';` and passed $error to closure
- **Files modified:** src/admin/clubs_handler.php
- **Committed in:** 5480d73 (Task 1 commit)

**2. [Rule 3 - Blocking] Added require for admin layout before render_admin_page call**
- **Found during:** Task 1 — comparing against teams_handler.php pattern
- **Issue:** Plan's code snippet omitted `require ROOT_PATH . '/src/templates/admin/layout.php'` but render_admin_page() is defined in that file; skipping it would fatal
- **Fix:** Added layout require to both clubs_handler.php and club_create_handler.php before calling render_admin_page()
- **Files modified:** src/admin/clubs_handler.php, src/admin/club_create_handler.php
- **Committed in:** 5480d73 (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (1 missing critical, 1 blocking)
**Impact on plan:** Both fixes necessary for correctness. No scope creep.

## Issues Encountered
None — implementation straightforward following existing team management pattern.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Clubs CRUD is complete; /admin/clubs is usable
- Plans 08-04 through 08-07 can reference clubs via SELECT from clubs table
- Spieler and Attribut-Gruppen nav links are in place, pointing to routes to be added in later plans

---
*Phase: 08-player-club-management*
*Completed: 2026-08-01*
