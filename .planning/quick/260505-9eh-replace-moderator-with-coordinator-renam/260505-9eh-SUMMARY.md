# Quick Task 260505-9eh — SUMMARY

**Task:** Replace moderator with coordinator (Koordinator) — rename src/coach→coordinator, src/player→member throughout
**Date:** 2026-05-05
**Commit:** e358217

## What was done

### Folder & file renames (git mv)
- `src/coach/` → `src/coordinator/`
- `src/player/` → `src/member/`
- `src/templates/coach/` → `src/templates/coordinator/`
- `src/templates/player/` → `src/templates/member/`
- `src/admin/coach_action_handler.php` → `src/admin/coordinator_action_handler.php`
- `src/admin/coach_create_handler.php` → `src/admin/coordinator_create_handler.php`
- `src/admin/coaches_handler.php` → `src/admin/coordinators_handler.php`
- `src/coordinator/player_action_handler.php` → `src/coordinator/member_action_handler.php`
- `src/coordinator/player_create_handler.php` → `src/coordinator/member_create_handler.php`
- `src/coordinator/players_handler.php` → `src/coordinator/members_handler.php`
- `src/templates/coordinator/player_form.php` → `src/templates/coordinator/member_form.php`
- `src/templates/coordinator/players.php` → `src/templates/coordinator/members.php`

### PHP code changes
- `src/auth/session.php`: `require_coach()` → `require_coordinator()`, role check `'moderator'` → `'coordinator'`
- `src/auth/login_handler.php`: bridge code maps `'moderator'` → `'coordinator'` at login (legacy value handling)
- `src/db/visibility.php`: role checks `'moderator'` → `'coordinator'`
- `src/db/connection.php`: all RLS policy strings, CHECK constraint, Migration 008 block added
- `public/index.php`: routes `/moderator/` → `/coordinator/`, file paths updated, `player_id` → `member_id` in route closure
- `src/coordinator/member_action_handler.php`: `$player_id` → `$member_id`, ownership check comments updated
- `src/coordinator/member_create_handler.php`: nav key `'players'` → `'members'`, template path updated
- `src/coordinator/members_handler.php`: nav key `'players'` → `'members'`, template path updated
- `src/admin/coordinators_handler.php`: complete rewrite — all `$coaches` vars → `$coordinators`, nav key `'coaches'` → `'coordinators'`
- `src/admin/coordinator_create_handler.php`: nav key `'coaches'` → `'coordinators'`, German title updated
- `src/templates/coordinator/layout.php`: nav key `'players'` → `'members'` in both sidebar and mobile tabs

### DB / RLS
- `database/rls_policies.sql`: all `'moderator'` → `'coordinator'`
- `database/schema.sql`: CHECK constraint updated
- `src/db/connection.php` Migration 008: auto-runs on boot — drops CHECK constraint, updates role values, adds new constraint, recreates all RLS policies

### German UI labels
All occurrences of "Moderator"/"Moderatoren" updated to "Koordinator"/"Koordinatoren" in:
- `src/templates/coordinator/list_detail.php`
- `src/templates/coordinator/list_form.php`
- `src/coordinator/list_settings_handler.php`
- `src/templates/member/lists.php`
- `src/templates/member/stats.php`
- `src/templates/admin/layout.php`
- `src/templates/admin/dashboard.php`
- `src/templates/admin/coach_form.php`
- `src/admin/coordinators_handler.php`
- `src/admin/coordinator_create_handler.php`
- `src/admin/settings_handler.php`

### Production migration
- `database/migration_008_moderator_to_coordinator.sql`: standalone idempotent SQL for pgAdmin
  - Requires `SET app.is_admin = 'true'` first (bypasses FORCE RLS)
  - Updates CHECK constraint, role values, all RLS policies

## Verification
- No `/moderator/` routes remain in index.php
- No `src/coach/` or `src/player/` directories remain
- `require_coordinator()` is the auth gate for all coordinator pages
- DB role value 'coordinator' used in all SQL and RLS policies
- German UI consistently shows "Koordinator"/"Koordinatoren"
