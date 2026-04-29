---
phase: 01-foundation
plan: 03
subsystem: ui, admin
tags: [php, bootstrap, pdo, csrf, admin, teams, coaches, password-reset]

# Dependency graph
requires:
  - phase: 01-01
    provides: "get_db(), require_admin(), csrf_field(), require_csrf(), e(), redirect(), generate_unique_username(), generate_random_password(), ROOT_PATH constant"
  - phase: 01-02
    provides: "render_layout_head(), render_navbar(), render_layout_foot() from src/templates/layout.php"
provides:
  - Admin dashboard showing all teams as Bootstrap Cards with coach counts
  - Team CRUD: create (INSERT), rename (UPDATE name), deactivate (UPDATE is_active=false)
  - Coach CRUD: create with auto-generated username (D-11) and random password, bcrypt cost 12
  - Password reset for coaches via admin — generates new password, renders credential modal
  - 60-second auto-close credential modal with Cache-Control: no-store, copy-to-clipboard buttons
  - Admin layout wrapper (render_admin_page) with desktop sidebar and mobile top-tab navigation
affects:
  - 02+ (coach/player management — admin area is now fully functional for Phase 1 scope)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - render_admin_page() callable body pattern — admin pages pass a closure, layout wraps with sidebar
    - Credential modal as full-page include (not Bootstrap modal) — avoids BS JS dependency for security display
    - Error propagation via GET ?error= redirect — simple flash for POST-redirect-GET pattern
    - coaches_handler.php inlines HTML inside closure rather than using a separate template file

key-files:
  created:
    - src/templates/admin/layout.php
    - src/templates/admin/dashboard.php
    - src/templates/admin/team_form.php
    - src/templates/admin/coach_form.php
    - src/templates/admin/credential_modal.php
    - src/admin/teams_handler.php
    - src/admin/coach_create_handler.php
    - src/admin/coach_action_handler.php
    - src/admin/coaches_handler.php
    - src/admin/team_create_handler.php
    - src/admin/team_action_handler.php
  modified: []

key-decisions:
  - "render_admin_page() takes a callable $body — handlers pass closures, keeping template logic together"
  - "credential_modal.php renders as full page overlay (modal show d-block), not a Bootstrap .modal — no JS dependency for display, Cache-Control: no-store set before any HTML"
  - "coaches_handler.php inlines the coaches list HTML directly inside the closure rather than extracting to a separate template — avoids an extra file for a single-purpose list"
  - "Error messages use GET ?error= redirect (POST-redirect-GET pattern) — prevents form resubmission on refresh"

patterns-established:
  - "Pattern 8: render_admin_page(title, active, callable) — all admin pages use this wrapper"
  - "Pattern 9: Credential display via full-page include with Cache-Control: no-store, never logged"

requirements-completed: [AUTH-04, TEAM-01, TEAM-02, TEAM-03]

# Metrics
duration: ~6min
completed: 2026-04-29
---

# Phase 01 Plan 03: Admin Panel (Team + Coach CRUD) Summary

**Bootstrap admin panel with team CRUD (create/rename/deactivate), coach creation with auto-generated usernames and bcrypt passwords, and a 60-second Cache-Control: no-store credential modal for password display**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-04-29T04:50:22Z
- **Completed:** 2026-04-29T04:56:00Z
- **Tasks:** 2
- **Files modified:** 11 created

## Accomplishments

- Admin layout with `render_admin_page()` callable pattern — desktop sidebar (Teams/Trainer) and mobile top tab bar, active nav item highlighted in accent blue
- Teams dashboard with Bootstrap Cards: each shows team name, coach count, edit modal, JS-confirmed deactivate form; Create Team modal with CSRF token
- Team handlers: create (INSERT with max-100 name validation), edit (UPDATE name), deactivate (UPDATE is_active=FALSE) — all with require_admin() + require_csrf()
- Coach creation handler: `generate_unique_username()` (initials+4-digit, D-11), `generate_random_password()`, `password_hash(PASSWORD_BCRYPT, cost:12)`, then shows credential modal — plaintext password never logged
- Coach password reset handler: regenerates password, updates hash, shows credential modal; logs coach id/username only
- Credential modal: full-page overlay with Cache-Control: no-store, 60s JS countdown, copy-to-clipboard buttons, redirects on timeout or manual close

## Task Commits

Each task was committed atomically:

1. **Task 1: Create admin templates (layout, dashboard, forms, credential modal)** - `cf9b5eb` (feat)
2. **Task 2: Create admin handler files (team CRUD, coach CRUD, password reset)** - `82c6085` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified

- `src/templates/admin/layout.php` - render_admin_page() wrapper with sidebar nav and mobile tab bar
- `src/templates/admin/dashboard.php` - Teams Bootstrap Cards with edit modal, deactivate form, and coaches list per team
- `src/templates/admin/team_form.php` - Standalone team rename form (GET fallback)
- `src/templates/admin/coach_form.php` - Coach creation form: Vorname, Nachname, Team select
- `src/templates/admin/credential_modal.php` - 60s countdown overlay, copy buttons, Cache-Control: no-store
- `src/admin/teams_handler.php` - require_admin, fetch all teams + coaches grouped by team_id, render dashboard
- `src/admin/team_create_handler.php` - POST only, CSRF guard, INSERT teams with name validation
- `src/admin/team_action_handler.php` - POST only, CSRF guard, UPDATE teams (edit name / set is_active=false)
- `src/admin/coaches_handler.php` - require_admin, LEFT JOIN teams for team_name, inline coaches list HTML
- `src/admin/coach_create_handler.php` - GET shows form; POST: validate, generate username+password, INSERT, show credential modal
- `src/admin/coach_action_handler.php` - POST only, verify coach role, reset password, show credential modal

## Decisions Made

- `render_admin_page()` takes a `callable $body` — handlers pass closures so content is co-located with the data it needs, avoiding scattered template variable injection
- Credential modal rendered as a full-page include (`modal show d-block`) not as a Bootstrap `.modal` — avoids Bootstrap JS for security-sensitive display, Cache-Control: no-store header set before any HTML output
- `coaches_handler.php` inlines the coaches list HTML directly inside the closure instead of a separate template — avoids an extra file for a single-purpose list; consistent with plan intent
- POST-redirect-GET pattern for errors — `?error=urlencode(...)` in redirect prevents form resubmission on browser refresh

## Deviations from Plan

None — plan executed exactly as written.

The plan's `coach_create_handler.php` sample called `render_layout_head` / `render_navbar` / `render_layout_foot` directly (without wrapping in `render_admin_page`) when showing the credential modal. This was intentional in the plan since the credential page is not a nav-aware admin page, so the pattern was followed as specified.

## Known Stubs

None — all handlers are fully wired to the database schema from Plan 01-01 and templates from this plan. All routes in public/index.php were already registered in Plan 01-01.

## Self-Check: PASSED

Files confirmed present:
- FOUND: src/templates/admin/layout.php
- FOUND: src/templates/admin/dashboard.php
- FOUND: src/templates/admin/team_form.php
- FOUND: src/templates/admin/coach_form.php
- FOUND: src/templates/admin/credential_modal.php
- FOUND: src/admin/teams_handler.php
- FOUND: src/admin/team_create_handler.php
- FOUND: src/admin/team_action_handler.php
- FOUND: src/admin/coaches_handler.php
- FOUND: src/admin/coach_create_handler.php
- FOUND: src/admin/coach_action_handler.php

Commits confirmed:
- FOUND: cf9b5eb (Task 1)
- FOUND: 82c6085 (Task 2)

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required. All handlers depend on the PostgreSQL database from Plan 01-01 (run schema.sql + rls_policies.sql if not already done).

## Next Phase Readiness

- Phase 1 is complete: all foundation requirements (AUTH-01 through AUTH-04, TEAM-01 through TEAM-03) are implemented
- Phase 2 (player management) can immediately use: require_auth(), require_admin(), get_db(), render_admin_page(), credential_modal.php
- Admin can now create teams and coaches — prerequisite for Phase 2 where coaches manage players
- No blockers

---
*Phase: 01-foundation*
*Completed: 2026-04-29*
