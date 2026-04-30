---
phase: quick-260430-rhh
plan: 01
subsystem: admin-ui
tags: [admin, teams, coaches, soft-delete, bootstrap-collapse]
dependency_graph:
  requires: []
  provides: [team-reactivate, coach-deactivate, coach-reactivate, admin-inactive-grouping]
  affects: [src/templates/admin/dashboard.php, src/admin/coaches_handler.php, src/admin/team_action_handler.php, src/admin/coach_action_handler.php, public/index.php]
tech_stack:
  added: []
  patterns: [Bootstrap collapse for grouping, POST-redirect-GET for actions, CSRF on all mutation forms]
key_files:
  created: []
  modified:
    - src/admin/team_action_handler.php
    - src/admin/coach_action_handler.php
    - public/index.php
    - src/templates/admin/dashboard.php
    - src/admin/coaches_handler.php
decisions:
  - Inactive teams show only reactivate button (no edit modal) — simplifies inactive card; editing an inactive team is an unusual use case
  - Inactive coaches show no password-reset button — resetting credentials for a deactivated account is unnecessary and confusing
  - Coach handler restructured from early-exit guard to full if/elseif chain — enables clean addition of deactivate/reactivate alongside reset-password
metrics:
  duration: ~10min
  completed_date: "2026-04-30"
  tasks: 2
  files: 5
---

# Quick Task 260430-rhh: Admin UI Active/Inactive Grouping for Teams and Coaches

**One-liner:** Soft-delete reversibility via deactivate/reactivate for teams and coaches, with Bootstrap collapse grouping to hide inactive items behind a toggle.

## What Was Built

Both admin list pages (teams and coaches) now:

1. Show active items at the top in the normal layout.
2. Render a collapsed "Inaktiv (N)" toggle beneath — visible only when at least one inactive item exists.
3. Present inactive items inside the collapse with muted styling and a "Reaktivieren" POST button.

### Teams (`/admin/teams`)
- Active team cards: unchanged markup, retain "Team deaktivieren" button.
- Inactive team cards: `border-secondary opacity-75`, "Deaktiviert" badge, "Reaktivieren" button — no edit modal (simplified).
- Route: `POST /admin/teams/{id}/reactivate` — sets `is_active = TRUE`.

### Coaches (`/admin/coaches`)
- Active coach rows: "Deaktivieren" (outline-warning) + "Passwort zurücksetzen" (outline-danger) buttons.
- Inactive coach rows: "Reaktivieren" button only — no password-reset (irrelevant for deactivated accounts).
- Routes: `POST /admin/coaches/{id}/deactivate` and `POST /admin/coaches/{id}/reactivate`.

### Router (`public/index.php`)
- Teams regex updated: `(edit|deactivate|reactivate)` — removed stale `reset-password` that teams never had.
- Coaches regex updated: `(deactivate|reactivate|reset-password)`.

### Coach handler (`coach_action_handler.php`)
- Restructured from early-exit `$action !== 'reset-password'` guard to proper `if/elseif` chain.
- All three actions covered: `reset-password`, `deactivate`, `reactivate`.

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 560b81c | feat: add reactivate for teams and deactivate/reactivate for coaches; update router |
| 2 | b15631c | feat: group active/inactive teams and coaches with Bootstrap collapse |

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- `src/admin/team_action_handler.php` — reactivate branch present
- `src/admin/coach_action_handler.php` — deactivate/reactivate branches present
- `public/index.php` — both regexes updated
- `src/templates/admin/dashboard.php` — active/inactive split with collapse
- `src/admin/coaches_handler.php` — active/inactive split with collapse
- Commits 560b81c and b15631c confirmed in git log
