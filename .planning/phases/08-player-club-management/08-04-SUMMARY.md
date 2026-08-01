---
phase: 08-player-club-management
plan: "04"
subsystem: admin
tags: [players, team-memberships, coordinator-teams, admin-crud]
dependency_graph:
  requires: [08-03]
  provides: [players-admin-crud, assign-team, link-user, coordinator-add-team]
  affects: [admin-nav, coordinator-management]
tech_stack:
  added: []
  patterns: [action-handler-pattern, accordion-inline-edit, prg-pattern]
key_files:
  created:
    - src/admin/players_handler.php
    - src/admin/player_create_handler.php
    - src/admin/player_action_handler.php
    - src/templates/admin/players.php
    - src/templates/admin/player_form.php
  modified:
    - src/admin/coordinator_action_handler.php
    - public/index.php
decisions:
  - "Inline edit via Bootstrap accordion collapse — no extra page needed, mobile-friendly"
  - "assign-team closes old membership (left_at=NOW) then inserts new row — history preserved"
  - "link-user clears old player_id link first, then sets new one — prevents orphaned links"
  - "coordinator add-team catches PDOException for unique constraint (coordinator already on team)"
metrics:
  duration_seconds: 1898
  completed: "2026-08-01T11:33:37Z"
  tasks_completed: 2
  files_created: 5
  files_modified: 2
---

# Phase 8 Plan 4: Players Admin CRUD Summary

**One-liner:** Admin player CRUD with club/team filter, membership history via left_at, user account linking, and coordinator multi-team assignment.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | players_handler + player_create_handler | dcba2d8 | players_handler.php, player_create_handler.php |
| 2 | player_action_handler + coordinator add-team + templates + routes | 0eb9923 | player_action_handler.php, coordinator_action_handler.php, players.php, player_form.php, index.php |

## What Was Built

**players_handler.php** — GET /admin/players with optional club_id + team_id query filters. LEFT JOINs team_memberships WHERE left_at IS NULL for current team, LEFT JOINs users for linked account info. Sorted by last_name, first_name.

**player_create_handler.php** — GET form + POST INSERT with RETURNING id. Creates player with nullable phone/contact_name/description. Optionally inserts team_memberships row if team_id > 0 selected.

**player_action_handler.php** — POST-only handler dispatching on action:
- `edit`: UPDATE players SET basic fields + nullable optional fields
- `assign-team`: UPDATE team_memberships SET left_at=NOW() for active row, then INSERT new row (history preserved)
- `link-user`: verifies role='member', clears any existing link on that player, sets player_id on chosen user (user_id=0 = unlink)

**coordinator_action_handler.php** — Extended with `add-team` branch: validates team is active, INSERT coordinator_teams (user_id, team_id, joined_at=NOW()), catches PDOException for unique constraint (already on team).

**players.php template** — Bootstrap accordion per player for inline edit/assign-team/link-user forms. Filter form at top (club + team dropdowns). Member users loaded once for link-user select across all cards.

**player_form.php template** — Create form with Vorname (required), Nachname (required), Klub dropdown (required), Team dropdown (optional), Telefon, Kontaktname, Beschreibung textarea.

**public/index.php** — Added 3 player routes (/admin/players, /admin/players/create, /admin/players/{id}/{action}) + extended coordinator action regex to include add-team.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all fields wired to DB queries.

## Self-Check: PASSED

Files created:
- src/admin/players_handler.php: FOUND
- src/admin/player_create_handler.php: FOUND
- src/admin/player_action_handler.php: FOUND
- src/templates/admin/players.php: FOUND
- src/templates/admin/player_form.php: FOUND

Commits:
- dcba2d8: feat(08-04): players_handler + player_create_handler
- 0eb9923: feat(08-04): player_action_handler, coordinator add-team, templates, routes
