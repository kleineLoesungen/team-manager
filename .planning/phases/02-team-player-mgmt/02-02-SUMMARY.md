---
phase: 02-team-player-mgmt
plan: "02"
subsystem: ui
tags: [php, bootstrap, pdo, rls, players, coach]

# Dependency graph
requires:
  - phase: 02-01
    provides: render_coach_page(), require_coach(), coach layout with sidebar/mobile tabs
  - phase: 01-03
    provides: credential_modal.php, generate_unique_username(), generate_random_password(), csrf_field()

provides:
  - GET /coach/players — Bootstrap card list of team players (active/inactive grouping)
  - GET /coach/players/create — Player creation form
  - POST /coach/players/create — Create player, show credential modal, redirect

affects:
  - 02-03 (player password reset + activate/deactivate action handlers)
  - 03-xx (list management will link from player cards)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "require_coach() → get_db() → query → render_coach_page() listing pattern"
    - "GET+POST in single handler: require_coach → process POST → fall through to render"
    - "<details>/<summary> for collapsible inactive sections (no JS)"

key-files:
  created:
    - src/coach/players_handler.php
    - src/coach/player_create_handler.php
    - src/templates/coach/players.php
    - src/templates/coach/player_form.php
  modified: []

key-decisions:
  - "RLS-only team isolation for player listing: require_coach() sets team context; no explicit team_id filter needed but query is readable as-is"
  - "Reuse admin credential_modal.php for player creation: consistent UX, no duplication"
  - "$_SESSION['team_id'] used for INSERT team_id: coach session already carries validated team context"

patterns-established:
  - "Coach listing handler: require_coach → get_db → prepare/execute → render_coach_page with closure that requires template"
  - "Player creation POST: require_csrf → validate → generate credentials → INSERT → modal render → exit"
  - "Active/inactive split via array_filter in template, inactive group behind <details> element"

requirements-completed: [TEAM-04]

# Metrics
duration: 5min
completed: 2026-04-29
---

# Phase 2 Plan 02: Player Listing and Creation Summary

**Bootstrap card player listing for coaches with RLS team isolation, auto-generated credentials, and 60-second one-time credential modal on creation**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-04-29T20:10:26Z
- **Completed:** 2026-04-29T20:15:00Z
- **Tasks:** 2
- **Files modified:** 4 created, 0 modified

## Accomplishments

- GET /coach/players renders team's players as Bootstrap cards; active players at top, inactive in collapsible `<details>` section
- GET /coach/players/create shows Vorname + Nachname form with CSRF protection
- POST /coach/players/create inserts player into coach's team, shows credential modal for 60 seconds, then redirects to /coach/players
- RLS team isolation via `require_coach()` calling `set_team_context()` — no cross-team data leak possible

## Task Commits

Each task was committed atomically:

1. **Task 1: Create players listing handler and card template** - `dee7a08` (feat)
2. **Task 2: Create player creation handler and form template (TEAM-04)** - `939ec56` (feat)

## Files Created/Modified

- `src/coach/players_handler.php` — GET /coach/players: require_coach, RLS-isolated player query, render_coach_page closure
- `src/coach/player_create_handler.php` — GET form + POST handler: credential generation, INSERT player, credential modal render
- `src/templates/coach/players.php` — Bootstrap card list: active cards grid + inactive collapsible `<details>` section with action buttons
- `src/templates/coach/player_form.php` — Two-field form (Vorname, Nachname) with CSRF, autocomplete hints, info text about auto-generated credentials

## SQL Query Used for Player Listing

```sql
SELECT id, first_name, last_name, username, is_active
FROM users
WHERE role = 'player'
ORDER BY is_active DESC, last_name, first_name
```

RLS (set by `require_coach()`) ensures only the current coach's team players are returned. The `is_active DESC` sort naturally places active players first without any PHP sorting.

## POST Flow: Player Creation

1. `require_csrf()` — validate CSRF token
2. Trim and validate `first_name` + `last_name` (both required)
3. `generate_unique_username($pdo, $first_name, $last_name)` — initials + 4-digit number, collision-checked
4. `generate_random_password()` — 12-char random from safe character set, never logged
5. `password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12])` — hashed for storage
6. `INSERT INTO users (team_id, role, ...)` with `role='player'` and `team_id` from `$_SESSION['team_id']`
7. Set `$credential_username`, `$credential_password`, `$redirect_url = '/coach/players'`
8. `render_layout_head` → `render_navbar` → `require credential_modal.php` → `render_layout_foot` → `exit`
9. Modal auto-redirects to `/coach/players` after 60 seconds

## Template Variables

**players.php receives:**
- `$players` — array of `[id, first_name, last_name, username, is_active]` rows
- `$error` — HTML-escaped error string (from `?error=` query param)

Template splits via `array_filter` into `$active_players` and `$inactive_players`.

**player_form.php receives:**
- `$error` — string (empty on GET, validation message on POST failure)

Form re-populates `first_name`/`last_name` fields from `$_POST` on validation failure.

## Decisions Made

- RLS-only team isolation: `require_coach()` sets PostgreSQL session variable so `WHERE role='player'` alone is sufficient; no explicit `AND team_id = ?` needed (though the query is still readable)
- Reusing `src/templates/admin/credential_modal.php` for player creation: consistent UX pattern, no template duplication
- `$_SESSION['team_id']` for INSERT: the coach session carries a validated team_id set at login; no DB re-query needed

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- /coach/players listing page is complete and ready for action handlers (reset-password, deactivate, reactivate) in Plan 02-03
- Action buttons are rendered in the card footer but point to unimplemented routes — Plan 02-03 will implement those handlers
- Credential modal reuse pattern is established for any future credential display needs

---
*Phase: 02-team-player-mgmt*
*Completed: 2026-04-29*
