---
phase: 02-team-player-mgmt
plan: "03"
subsystem: coach-player-actions
tags: [auth, player-management, password-reset, coach]
dependency_graph:
  requires: [02-01, 02-02]
  provides: [AUTH-03, player-deactivate, player-reactivate]
  affects: [users table, coach player UI]
tech_stack:
  added: []
  patterns: [ownership-check, credential-modal-reuse, POST-redirect-GET, switch-action-dispatch]
key_files:
  created:
    - src/coach/player_action_handler.php
  modified: []
decisions:
  - "Ownership check uses triple constraint (id + team_id + role='player') to prevent cross-team access even if RLS is bypassed"
  - "Reuse admin credential_modal.php for player password reset — consistent UX, no template duplication across roles"
  - "switch() action dispatch matches existing admin handler pattern for consistency"
metrics:
  duration: "5 minutes"
  completed: "2026-04-29"
  tasks_completed: 1
  files_created: 1
  files_modified: 0
---

# Phase 02 Plan 03: Player Action Handler Summary

**One-liner:** POST handler for reset-password (AUTH-03), deactivate, reactivate player actions with triple-constraint ownership check (id + team_id + role='player').

## What Was Built

`src/coach/player_action_handler.php` — single file handling all three coach-to-player POST actions:

1. **reset-password** (AUTH-03): Generates a new random password, hashes it with bcrypt (cost=12), updates the `users` row, and renders the credential modal for 60 seconds. Plaintext password never logged.
2. **deactivate**: Sets `is_active = FALSE` for the player row, then redirects to `/coach/players`.
3. **reactivate**: Sets `is_active = TRUE` for the player row, then redirects to `/coach/players`.

## Ownership Check Pattern

Every action validates the player using a triple constraint:

```sql
SELECT id, username, first_name, last_name
FROM users
WHERE id = ? AND team_id = ? AND role = 'player'
```

The `team_id` is sourced from `$_SESSION['team_id']` (set at login and immutable for the session). If no matching row is found, the handler redirects to `/coach/players` without revealing whether the player exists.

The UPDATE statements also include `AND team_id = ? AND role = 'player'` as defense-in-depth to prevent acting on non-player rows even if the SELECT check were somehow bypassed.

## Password Reset Flow (AUTH-03)

```
POST /coach/players/{id}/reset-password
  → require_coach() (RLS team context set)
  → require_csrf()
  → ownership check (id + team_id + role='player')
  → generate_random_password()
  → password_hash(plain, BCRYPT, cost=12)
  → UPDATE users SET password_hash = ?
  → error_log(player id + username — no plaintext)
  → render credential_modal.php (60s countdown, then /coach/players)
```

## Credential Modal

Reuses `src/templates/admin/credential_modal.php` with:
- `$credential_username` = player's username
- `$credential_password` = the generated plain password (never logged)
- `$redirect_url` = '/coach/players'

Sets `Cache-Control: no-store` header before HTML output.

## Deviations from Plan

None — plan executed exactly as written.

## Commits

| Task | Description | Hash |
|------|-------------|------|
| 1 | Create player_action_handler.php (AUTH-03) | c504db9 |

## Self-Check: PASSED

- `src/coach/player_action_handler.php` exists: FOUND
- Commit c504db9 exists: FOUND
- PHP syntax clean (`php -l` exits 0): PASSED
- `require_coach()` present: PASSED
- Triple-constraint ownership check present: PASSED
- `credential_modal.php` required: PASSED
- No plaintext password in error_log: PASSED
