---
phase: 08-player-club-management
plan: "06"
subsystem: coordinator-player-views
tags: [coordinator, players, profile, attributes, cross-team-stats, directory, nav]
dependency_graph:
  requires: [08-02, 08-05]
  provides: [coordinator-player-list, coordinator-player-profile, coordinator-attribute-edit, coordinator-directory]
  affects: [coordinator-nav, coordinator-routes]
tech_stack:
  added: []
  patterns:
    - set_admin_context + immediate reset_rls_context + set_team_context for cross-team queries
    - INSERT ... ON CONFLICT DO UPDATE for player attribute value UPSERT
    - team_memberships LEFT JOIN with WHERE tm.player_id IS NOT NULL for team-scoped player listing
key_files:
  created:
    - src/coordinator/players_handler.php
    - src/coordinator/player_profile_handler.php
    - src/coordinator/player_attribute_edit_handler.php
    - src/coordinator/coordinators_handler.php
    - src/templates/coordinator/players.php
    - src/templates/coordinator/player_profile.php
    - src/templates/coordinator/coordinators.php
  modified:
    - src/templates/coordinator/layout.php
    - public/index.php
decisions:
  - "Player listing uses LEFT JOIN team_memberships + WHERE tm.player_id IS NOT NULL — avoids anti-pattern of team_id on players table"
  - "Cross-team stats bypass via set_admin_context immediately reset with reset_rls_context + set_team_context after single query"
  - "Coordinator directory is read-only (no POST forms) — locked decision per CONTEXT.md D-coordinator-phone"
  - "Spieler + Koordinatoren nav items inserted between Mitglieder and Statistik in sidebar and mobile tab bar"
metrics:
  duration_minutes: 70
  completed_date: "2026-08-02"
  tasks_completed: 3
  tasks_total: 3
  files_created: 7
  files_modified: 2
---

# Phase 08 Plan 06: Coordinator Player Views + Directory Summary

Coordinator-facing player list, profile page with EAV attributes and cross-team stats, attribute UPSERT handler, and a read-only coordinator directory with name + phone.

## What Was Built

### Task 1: players_handler.php + player_profile_handler.php (commit 8800718)

**players_handler.php** — Lists all players currently on the coordinator's team via `team_memberships WHERE left_at IS NULL`. Uses LEFT JOIN pattern to scope players without adding `team_id` directly to the `players` table (anti-pattern per RESEARCH.md). Includes club name, phone, contact name, and linked user account badge.

**player_profile_handler.php** — Full player profile loader:
- Player data + club name
- Full team membership history (all teams, ordered newest first)
- All attribute groups and values (coordinator sees all; `visible_to_player` filter is NOT applied)
- Linked user account lookup
- Cross-team stats via `set_admin_context()` bypass: one query under admin context, immediately followed by `reset_rls_context()` + `set_team_context()` — admin window is minimal

### Task 2: Templates + attribute edit handler + Spieler nav + routes (commit fc43bb2)

**player_attribute_edit_handler.php** — POST handler for saving player attribute values. Verifies player is on coordinator's team (defense-in-depth). Saves all submitted values via `INSERT ... ON CONFLICT (player_id, attribute_id) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()` UPSERT.

**players.php** — Mobile-first list-group view. Shows last name, first name, club, phone, contact person, and linked user badge. Links each entry to `/coordinator/players/{id}`.

**player_profile.php** — Full profile template:
- Header card: name, club, active team membership, phone, contact person, linked account badge
- Description section (shown when non-empty)
- Team membership history table (all teams, joined/left dates)
- Attribute form: one Bootstrap card per group, text inputs per attribute with `name="values[{attr_id}]"`, CSRF token, Speichern button
- Cross-team stats table (team, date, column name, value) or informational message when no account/no data

**layout.php** — Spieler nav item added after Mitglieder in sidebar (`active='players'`) and mobile tab bar.

**index.php** — Player routes added: `/coordinator/players`, `/coordinator/players/{id}/attributes/save` (BEFORE catch-all), `/coordinator/players/{id}`.

### Task 3: Coordinator directory (commit 0e6d48f)

**coordinators_handler.php** — GET handler. Queries `users WHERE role='coordinator' AND team_id=? AND is_active=TRUE`. Read-only — no POST handling.

**coordinators.php** — Read-only list-group template. Name (first + last) and phone number (tel: link). If no phone: "Keine Telefonnummer hinterlegt". Zero POST forms — pure display.

**layout.php** — Koordinatoren nav item added after Spieler in sidebar (`active='coordinators'`) and mobile tab bar. Final nav order in Team section: Mitglieder → Spieler → Koordinatoren → Statistik.

**index.php** — `/coordinator/coordinators` route added after player routes.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all data is wired to live queries. Templates render empty states ("Noch keine Spieler", "Kein Benutzeraccount verknüpft", etc.) when data is absent but no hardcoded placeholder values flow to UI rendering.

## Self-Check: PASSED

All 7 created files exist. All 3 task commits verified in git log (8800718, fc43bb2, 0e6d48f).
