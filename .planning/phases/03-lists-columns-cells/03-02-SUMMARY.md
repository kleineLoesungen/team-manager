---
phase: 03-lists-columns-cells
plan: "02"
subsystem: navigation-routing
tags: [nav, routing, player-layout, session, php]

dependency_graph:
  requires:
    - 03-01 (EAV schema, visibility helpers, session.php with require_player stub)
  provides:
    - render_coach_page() with 'lists' and 'columns' active nav states
    - render_player_page() with single 'Listen' nav item
    - require_player() session middleware (was already in session.php from 03-01)
    - All Phase 3 router stubs in public/index.php
  affects:
    - 03-03 (coach list handlers depend on /coach/lists/* routes)
    - 03-04 (coach column handlers depend on /coach/columns/* routes)
    - 03-05 (player handlers depend on /player/* routes and render_player_page())

tech_stack:
  added: []
  patterns:
    - Same sidebar + mobile-tabs dual-nav pattern as coach layout (replicated for player)
    - Router stubs: routes declared before handler files exist (plans 03-05 add handlers)

key_files:
  created:
    - src/templates/player/layout.php
  modified:
    - src/templates/coach/layout.php
    - src/auth/login_handler.php
    - public/index.php

decisions:
  - "Player layout mirrors coach layout structure: sidebar (desktop) + mobile tabs, single Listen nav item"
  - "require_player() was already implemented in 03-01 with full role+user_id RLS context — no change needed"
  - "set_team_context() already supported role+user_id params from 03-01 — no change needed"
  - "Router stubs added without stub handler files — PHP file-not-found until plans 03-05 complete"

metrics:
  duration: "~2 minutes"
  completed: "2026-04-30"
  tasks_completed: 2
  files_modified: 4
---

# Phase 3 Plan 2: Navigation, Player Layout & Route Stubs Summary

**One-liner:** Coach nav extended with Listen/Spalten items, player layout created with render_player_page() and single-item nav, login redirect fixed to /player/lists, and all Phase 3 router stubs added to public/index.php in correct priority order.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Update coach nav, create player layout, fix login redirect | 0266e20 | src/templates/coach/layout.php, src/templates/player/layout.php, src/auth/login_handler.php |
| 2 | Extend router with all Phase 3 route stubs | 32a1d26 | public/index.php |

## What Was Built

### src/templates/coach/layout.php
- Added "Listen" nav item (active key: 'lists', href: /coach/lists, icon: bi-table) to sidebar and mobile tabs
- Added "Spalten" nav item (active key: 'columns', href: /coach/columns, icon: bi-layout-three-columns) to sidebar and mobile tabs
- Updated file comment from "Phase 2 only players" to "Phase 3 adds lists and columns"
- Updated docblock: $active now accepts 'players', 'lists', or 'columns'

### src/templates/player/layout.php (new)
- New file mirroring coach layout structure with Bootstrap 5 sidebar + mobile tabs pattern
- Function: `render_player_page(string $title, string $active, callable $body): void`
- Single nav item: "Listen" (active key: 'lists', href: /player/lists, icon: bi-table)
- Requires parent layout.php for render_layout_head(), render_navbar(), render_layout_foot()

### src/auth/session.php
- `require_player()` was already implemented in plan 03-01 with full RLS context (role + user_id)
- No changes needed — plan's action E verified this was already complete

### src/auth/login_handler.php
- Fixed is_authenticated() redirect for players: `redirect('/player')` → `redirect('/player/lists')`
- Fixed post-login role redirect for players: `redirect('/player')` → `redirect('/player/lists')`
- Both occurrences updated; no old `/player` redirect remains

### src/db/connection.php
- Already supported role + user_id params in set_team_context() from plan 03-01
- No changes needed

### public/index.php
Added all Phase 3 routes between "Coach: Players" and "404" default, in correct specificity order:

**Coach list routes (8 routes):**
- `$path === '/coach/lists'` → lists_handler.php
- `$path === '/coach/lists/create'` → list_create_handler.php
- `/coach/lists/{id}/settings` → list_settings_handler.php
- `/coach/lists/{id}/columns/create` → list_column_create_handler.php
- `/coach/lists/{id}/rows/{player_id}/edit` → list_row_edit_handler.php
- `/coach/lists/{id}` → list_detail_handler.php (after more specific patterns)
- `$path === '/coach/columns'` → columns_handler.php
- `$path === '/coach/columns/create'` → columns_create_handler.php

**Player routes (3 routes):**
- `$path === '/player' || $path === '/player/lists'` → player/lists_handler.php
- `/player/lists/{id}/rows/{player_id}/edit` → player/list_row_edit_handler.php
- `/player/lists/{id}` → player/list_detail_handler.php (after more specific pattern)

## Deviations from Plan

### Pre-existing Implementation (Not a Bug)

Plan Task 1 action C specified "Append require_player() to session.php". During execution, require_player() was found to already exist in session.php (added in plan 03-01 as an auto-fix). The function already implements the correct signature with full RLS context (role + user_id). No action taken — pre-existing implementation satisfies all acceptance criteria.

Similarly, set_team_context() in connection.php already had the role + user_id parameters from 03-01. No changes needed.

## Known Stubs

The router stubs reference handler files that do not yet exist (src/coach/lists_handler.php, src/coach/list_create_handler.php, etc.). These will be created in plans 03-03, 03-04, and 03-05. Until then, routes return PHP file-not-found errors. This is intentional per plan design — handlers are added independently per plan.

## Self-Check: PASSED
