---
phase: 01-foundation
plan: 02
subsystem: auth, ui
tags: [php, bootstrap, session, csrf, login, logout, templates]

# Dependency graph
requires:
  - phase: 01-01
    provides: "config.php constants (ADMIN_USERNAME, ADMIN_PASSWORD_HASH, SESSION_TIMEOUT), session.php (start_secure_session, is_admin, is_authenticated, require_csrf), db/connection.php (get_db, set_team_context), utils/csrf.php (csrf_field, require_csrf), utils/helpers.php (e, redirect), public/index.php routing /login and /logout"
provides:
  - Login GET/POST handler supporting admin (config.php) and DB users with CSRF validation, session fixation prevention, inactive-user guard, and German error messages
  - Logout handler that clears session vars, expires cookie with Strict samesite, and calls session_destroy()
  - Shared HTML layout functions (render_layout_head, render_navbar, render_layout_foot, render_login_page) with Bootstrap 5.3 CDN
  - Login page template with German labels, CSRF hidden field, alert-danger / alert-info feedback, autocomplete attributes
affects:
  - 01-03 (coach auth — depends on login/logout flows and layout functions)
  - all subsequent plans (all use layout.php for page structure)

# Tech tracking
tech-stack:
  added:
    - Bootstrap 5.3 CSS via jsDelivr CDN (integrity hash)
    - Bootstrap Icons 1.11.0 via jsDelivr CDN
    - Bootstrap 5.3 JS bundle via jsDelivr CDN (integrity hash)
  patterns:
    - render_layout_head() / render_layout_foot() wrapping pattern — all pages call these
    - render_login_page() convenience wrapper passes $error and $message to template
    - Separate template file (login.php) included via require inside render_login_page()
    - Admin login via config constants, DB user login via prepared statement — no shared code path
    - Session fixation prevention via session_regenerate_id(true) immediately after credential verification
    - Vague error messages ("Benutzername oder Passwort falsch") — no per-field disclosure

key-files:
  created:
    - src/auth/login_handler.php
    - src/auth/logout_handler.php
    - src/templates/layout.php
    - src/templates/login.php
  modified: []

key-decisions:
  - "render_login_page() in layout.php rather than a separate file — keeps layout contract centralized"
  - "Bootstrap 5.3 loaded via CDN with SRI integrity hashes — no build step, tamper-resistant delivery"
  - "Vague error message for both wrong username and wrong password — no information disclosure"
  - "session_regenerate_id(true) called before setting any session vars — prevents session fixation"

patterns-established:
  - "Pattern 6: All pages use render_layout_head() + render_layout_foot() from layout.php"
  - "Pattern 7: render_login_page($error, $message) is the canonical way to render the login form"

requirements-completed: [AUTH-01, AUTH-02]

# Metrics
duration: ~2min
completed: 2026-04-29
---

# Phase 01 Plan 02: Login, Logout and Shared Layout Summary

**Bootstrap 5.3 login page with CSRF-protected POST handler for admin (config.php) and DB users, session fixation prevention via session_regenerate_id(true), inactive-user guard, and German error messages**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-04-29T04:41:15Z
- **Completed:** 2026-04-29T04:42:36Z
- **Tasks:** 2
- **Files modified:** 4 created

## Accomplishments

- Login handler handles both admin path (ADMIN_PASSWORD_HASH constant) and DB user path (prepared statement + password_verify), both guarded by CSRF validation and session fixation prevention
- Inactive users (is_active=false) receive a German-language error; wrong credentials get a deliberately vague message that doesn't reveal which field failed
- Shared layout functions (render_layout_head, render_navbar, render_layout_foot) with Bootstrap 5.3 + Icons CDN establish the visual baseline for all subsequent templates
- Login page template uses German labels, Bootstrap card layout, alert-danger/alert-info feedback zones, and proper autocomplete attributes

## Task Commits

Each task was committed atomically:

1. **Task 1: Create login and logout handlers** - `650464b` (feat)
2. **Task 2: Create shared layout template and login page template** - `b672d05` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified

- `src/auth/login_handler.php` - Login GET/POST handler: CSRF guard, admin path, DB user path with is_active check, session_regenerate_id(true), German error messages
- `src/auth/logout_handler.php` - Logout handler: clear $_SESSION, expire cookie with Strict samesite, session_destroy(), redirect /login
- `src/templates/layout.php` - Shared layout functions: render_layout_head (Bootstrap 5.3 CDN), render_navbar (role badge + Abmelden), render_layout_foot (Bootstrap JS), render_login_page wrapper
- `src/templates/login.php` - Login form: German labels, CSRF hidden field, alert-danger/alert-info, POST /login, autocomplete attributes

## Decisions Made

- render_login_page() lives in layout.php (not a separate controller file) — single location for the page assembly contract
- Bootstrap 5.3 loaded from jsDelivr CDN with SRI integrity hashes — no npm/build pipeline needed, tamper-resistant
- Error messages are deliberately vague — "Benutzername oder Passwort falsch" for all credential failures
- session_regenerate_id(true) is called immediately after verifying credentials, before writing any session vars

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

- `redirect('/dashboard')` in login_handler.php — /dashboard route does not exist yet (Phase 2). After successful DB user login, user is redirected to /dashboard which will 404 until Plan 03+ implements the dashboard. This is intentional; the routing stub is noted in the code comment "Phase 2+".

## Self-Check: PASSED

Files confirmed present:
- FOUND: src/auth/login_handler.php
- FOUND: src/auth/logout_handler.php
- FOUND: src/templates/layout.php
- FOUND: src/templates/login.php

Commits confirmed:
- FOUND: 650464b (Task 1)
- FOUND: b672d05 (Task 2)
