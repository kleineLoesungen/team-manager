---
phase: 01-foundation
plan: 01
subsystem: database, auth, infra
tags: [php, postgresql, pdo, rls, csrf, session, bootstrap]

# Dependency graph
requires: []
provides:
  - PostgreSQL schema with users and teams tables and all required columns
  - Row-Level Security policies on users table (SELECT, INSERT, UPDATE) via app.current_team_id
  - PDO connection factory (get_db()) with ATTR_EMULATE_PREPARES=false and schema isolation
  - CSRF token generation/validation helpers using random_bytes and hash_equals
  - Shared utility functions (e(), redirect(), generate_random_password(), generate_unique_username())
  - Secure session bootstrap (start_secure_session()) with OWASP-compliant cookie settings
  - Session timeout middleware with 8-hour sliding window (check_session_timeout())
  - Auth middleware functions (require_auth(), require_admin(), is_admin(), is_authenticated())
  - Front controller router (public/index.php) dispatching all HTTP requests to handlers
  - Application config with env-based constants (ADMIN_USERNAME, DB_*, SESSION_TIMEOUT)
affects:
  - 01-02 (admin auth — depends on require_admin, config constants, session bootstrap)
  - 01-03 (coach auth — depends on require_auth, session functions)
  - all subsequent plans (all use get_db(), e(), redirect(), csrf helpers, session)

# Tech tracking
tech-stack:
  added:
    - PHP 8.3+ native session management
    - PDO with pdo_pgsql driver
    - PostgreSQL RLS (Row-Level Security) via session variable app.current_team_id
  patterns:
    - Singleton PDO via static variable in get_db()
    - Front controller pattern — all requests routed through public/index.php
    - match() expression for URL dispatch in PHP 8+
    - Sliding-window session timeout (last_activity timestamp updated each request)
    - OWASP session hardening (strict_mode, httponly, samesite=Strict, sid_length=256)
    - Admin stored in config.php — no users table row for admin (D-02)
    - RLS team isolation via PostgreSQL session variable, set_team_context() helper
    - Output escaping via e() wrapper on htmlspecialchars()
    - CSRF via session token + hash_equals() timing-safe comparison

key-files:
  created:
    - config.php
    - .env.example
    - database/schema.sql
    - database/rls_policies.sql
    - src/db/connection.php
    - src/utils/csrf.php
    - src/utils/helpers.php
    - src/auth/session.php
    - public/index.php
  modified: []

key-decisions:
  - "Admin credentials live in config.php/env vars, not users table (D-02) — eliminates auth bootstrapping problem"
  - "PDO ATTR_EMULATE_PREPARES=false mandatory for PostgreSQL — native prepared statements, no type coercion"
  - "SESSION_TIMEOUT = 8 hours sliding window (D-05) — balance security and usability for coaches"
  - "cookie_samesite=Strict and cookie_httponly=true prevent CSRF and XSS session hijacking"
  - "DB_SCHEMA constant isolates team_manager tables from other apps sharing same PostgreSQL database"
  - "RLS policies use app.current_team_id session variable; admin bypasses at application layer"
  - "match() expression for front controller — exhaustive, returns value, cleaner than switch/if chains"

patterns-established:
  - "Pattern 1: All PHP output uses e() for XSS prevention"
  - "Pattern 2: All POST handlers call require_csrf() at entry point"
  - "Pattern 3: All protected routes call require_auth() or require_admin() before any logic"
  - "Pattern 4: PDO always obtained via get_db() singleton — never instantiate PDO directly"
  - "Pattern 5: New handler files added to match() in public/index.php — no .htaccess rewrites per handler"

requirements-completed: [AUTH-01, AUTH-02, TEAM-01, TEAM-02, TEAM-03]

# Metrics
duration: 25min
completed: 2026-04-29
---

# Phase 01 Plan 01: Foundation Infrastructure Summary

**PostgreSQL schema with RLS team isolation, PDO singleton factory, CSRF/session/helper utilities, and PHP front controller routing all HTTP requests via match() expression**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-04-29T06:00:00Z
- **Completed:** 2026-04-29T06:25:00Z
- **Tasks:** 3
- **Files modified:** 9 created

## Accomplishments

- Database schema with teams and users tables, indexes, and RLS SELECT/INSERT/UPDATE policies via `app.current_team_id` session variable
- PDO connection helper with security-mandatory `ATTR_EMULATE_PREPARES=false`, schema isolation, and team context setter for RLS
- CSRF utility (get_csrf_token, validate_csrf_token, csrf_field, require_csrf) using `random_bytes` and `hash_equals` for timing-safe comparison
- Shared helpers: e() for output escaping, redirect(), generate_random_password(), generate_unique_username() with collision retry
- Secure session bootstrap with OWASP cookie settings (strict_mode, httponly, samesite=Strict, sid_length=256, 8h timeout)
- Auth middleware: require_auth(), require_admin(), is_admin(), is_authenticated() with sliding-window timeout
- Front controller in public/index.php dispatching /login, /logout, /admin/teams, /admin/coaches routes via PHP 8 match()

## Task Commits

Each task was committed atomically:

1. **Task 1: Create directory structure, config, and database schema** - `9128dbe` (feat)
2. **Task 2: Create PDO connection helper, CSRF utility, and shared helpers** - `d759826` (feat)
3. **Task 3: Create front controller router with session bootstrap and auth middleware** - `2527dad` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified

- `config.php` - Application configuration with env-based constants (ADMIN_USERNAME, DB_*, SESSION_TIMEOUT)
- `.env.example` - Environment variable template for deployment
- `database/schema.sql` - teams and users tables with CHECK constraint on role, UNIQUE on username, indexes
- `database/rls_policies.sql` - RLS ENABLE + SELECT/INSERT/UPDATE policies on users table using current_setting()
- `src/db/connection.php` - get_db() PDO singleton, set_team_context() for RLS session variable
- `src/utils/csrf.php` - get_csrf_token(), validate_csrf_token(), csrf_field(), require_csrf()
- `src/utils/helpers.php` - e(), redirect(), generate_random_password(), generate_username(), generate_unique_username()
- `src/auth/session.php` - start_secure_session(), check_session_timeout(), require_auth(), require_admin(), is_admin(), is_authenticated()
- `public/index.php` - Front controller: defines ROOT_PATH, bootstraps all shared files, routes requests

## Decisions Made

- Admin stored in config.php/env only (D-02): no users table row for admin, eliminates chicken-and-egg auth bootstrapping
- `ATTR_EMULATE_PREPARES=false` is non-negotiable for PostgreSQL — emulation bypasses native type safety
- 8-hour sliding session window chosen per D-05 for coach convenience during training sessions
- `cookie_samesite=Strict` chosen over `Lax` for maximum CSRF resistance (app does not need cross-site GET flows)
- DB_SCHEMA constant allows running multiple app instances in one PostgreSQL database

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed match() expression closing delimiter in public/index.php**
- **Found during:** Task 3 (front controller creation)
- **Issue:** The plan's code sample used `];` to close the match expression, but match() is a PHP expression and must end with `};` — using `];` causes a parse error
- **Fix:** Changed closing `];` to `};` in public/index.php
- **Files modified:** public/index.php
- **Verification:** `php -l public/index.php` reports no syntax errors
- **Committed in:** 2527dad (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 bug — syntax error in plan's code sample)
**Impact on plan:** Fix essential for file to parse. No scope change.

## Issues Encountered

- The plan's code sample for public/index.php used `];` to close the `match()` block. PHP requires `};` for match expressions. Fixed as Rule 1 auto-fix before committing.

## User Setup Required

None — no external service configuration required. Database setup is manual (run schema.sql then rls_policies.sql as PostgreSQL superuser), but no managed service accounts are needed.

## Next Phase Readiness

- All shared infrastructure is in place: DB connection, session, CSRF, helpers, routing
- Plan 02 (admin auth) can immediately implement login_handler.php and logout_handler.php — all required functions exist
- Plan 03 (coach auth) can use require_auth() and session functions without modification
- RLS set_team_context() is ready but will only be called once coach team_id is known (Plan 03+)
- No blockers

---
*Phase: 01-foundation*
*Completed: 2026-04-29*
