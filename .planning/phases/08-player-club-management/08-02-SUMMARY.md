---
phase: 08-player-club-management
plan: "02"
subsystem: auth
tags: [multi-team, coordinator, session, login-flow]
dependency_graph:
  requires: [08-01]
  provides: [multi-team-coordinator-login, team-picker-ui, switch-team-flow]
  affects: [src/auth, src/coordinator, public/index.php]
tech_stack:
  added: []
  patterns: [POST-redirect-GET, pending_team_pick session flag, set_admin_context for cross-team queries]
key_files:
  created:
    - src/coordinator/select_team_handler.php
    - src/templates/coordinator/select_team.php
  modified:
    - src/auth/login_handler.php
    - src/auth/session.php
    - src/admin/coordinator_create_handler.php
    - public/index.php
decisions:
  - "pending_team_pick session flag pattern: set at login, cleared in select_team POST, guards all require_coordinator() calls"
  - "available_teams stored in session to avoid re-querying coordinator_teams on every select-team GET"
  - "set_admin_context used in select_team_handler to bypass RLS for cross-team validation and users.team_id update"
  - "Both /coordinator/select-team and /coordinator/switch-team map to same handler; str_ends_with detects context"
metrics:
  duration_seconds: 235
  completed_date: "2026-08-01"
  tasks_completed: 3
  files_changed: 6
---

# Phase 8 Plan 02: Multi-Team Coordinator Login Flow Summary

**One-liner:** Multi-team coordinator login via pending_team_pick session flag → team picker page; single-team coordinators and members completely unaffected.

## What Was Built

Coordinators who manage multiple active teams are now redirected to `/coordinator/select-team` after login instead of landing on `/coordinator/members` directly. A new session flag (`pending_team_pick`) gates all coordinator pages until a team is selected. After selection, `users.team_id` is updated in the database and the session is fully initialized.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Login flow + coordinator_teams on create | 6ffd45c | src/auth/login_handler.php, src/admin/coordinator_create_handler.php |
| 2 | require_coordinator() guard + select_team_handler | 441eb96 | src/auth/session.php, src/coordinator/select_team_handler.php |
| 3 | Team picker template + routing | 121c186 | src/templates/coordinator/select_team.php, public/index.php |

## Implementation Details

### Login Flow (Task 1 — committed in prior execution)
After `password_verify` succeeds for a coordinator, the handler queries `coordinator_teams` using `set_admin_context` to get all active team memberships. If count > 1: sets `pending_team_pick = true`, stores `available_teams` in session, redirects to `/coordinator/select-team`. If count <= 1: falls through to the existing single-team session setup unchanged.

### require_coordinator() Guard (Task 2)
The `pending_team_pick` check is the first statement in `require_coordinator()`, executing before `check_session_timeout()`. This ensures any coordinator page hit while pending redirects to the picker without setting RLS context (which would fail since `team_id` is not yet set in session).

### select_team_handler.php (Task 2)
Handles GET (render picker) and POST (validate + switch). POST validates `team_id` against `$_SESSION['available_teams']` to prevent CSRF-bypass team escalation, updates `users.team_id` via `set_admin_context`, clears `pending_team_pick`, and redirects to `/coordinator/members`. GET populates `available_teams` from `coordinator_teams` if not in session (used by switch-team flow for already-logged-in coordinators).

### Template + Routes (Task 3)
`select_team.php` uses `render_layout_head/navbar/foot` directly (no sidebar — picker is pre-navigation state). Shows "Team auswählen" for post-login flow and "Team wechseln" for in-session switch. Both `/coordinator/select-team` and `/coordinator/switch-team` routes registered in `index.php`.

## Deviations from Plan

### Pre-existing Task 1 completion
Task 1 changes to `login_handler.php` and `coordinator_create_handler.php` were already committed as `6ffd45c feat(08-02): multi-team coordinator login flow + coordinator_teams on create` from a prior partial execution. Those changes match the plan exactly — no rework was needed.

### csrf_field() instead of csrf_input()
Plan specified `csrf_input()` in the template, but the actual CSRF utility in `src/utils/csrf.php` exposes `csrf_field()`. Used the correct existing function name. [Rule 1 - Bug]

## Known Stubs

None — the feature is fully wired: coordinator_teams is queried at login, available_teams populates the picker, POST updates users.team_id and sets session.

## Self-Check: PASSED

Files created:
- src/coordinator/select_team_handler.php — FOUND
- src/templates/coordinator/select_team.php — FOUND

Commits:
- 6ffd45c — FOUND (Task 1, prior execution)
- 441eb96 — FOUND (Task 2)
- 121c186 — FOUND (Task 3)
