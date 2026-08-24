# Quick Task 260823-pbq: rename player to member (backend, db, UI) - Context

**Gathered:** 2026-08-23
**Status:** Ready for planning

<domain>
## Task Boundary

Rename all "player" terminology to "member" across the full stack: database tables/columns,
PHP code (functions, variables, queries), URL routes, and UI text.

</domain>

<decisions>
## Implementation Decisions

### DB layer
- Rename the `players` table to `members`
- Rename `player_attribute_groups` → `member_attribute_groups`
- Rename `player_attributes` → `member_attributes`
- Rename `player_attribute_values` → `member_attribute_values`
- Rename `users.player_id` → `users.member_id` (FK to members/profiles table)
- Rename `cells.player_id` → `cells.member_id` (FK to users.id — who owns the cell)
- Production migration is required and must be guaranteed safe:
  - Wrap all renames in a transaction inside `maybe_migrate_db()`
  - PostgreSQL FK constraints follow table/column renames automatically
  - RLS policies that reference `player_id` column by name must be dropped and recreated
  - `schema.sql` and `rls_policies.sql` updated to match new names

### PHP function names
- `require_player()` → `require_member()` (rename in helpers.php + all ~50 call sites)
- `render_player_page()` → `render_member_page()` (rename in helpers.php + all call sites)
- `render_coach_page()` stays as-is (coordinator layout, unrelated)
- All PHP variables `$player`, `$players`, `$player_id`, `$player_ids` → `$member`, etc.
- SQL strings: `player_id` column references → `member_id`; `FROM players` → `FROM members`; etc.
- `prefill_number_cells()` helper: `player_id` → `member_id` in SQL

### URL paths
- `/coordinator/player-profile` → `/coordinator/member-profile`
- `/member/player-profile` → `/member/member-profile`
- Update routing in `public/index.php` and all `href` references in templates
- No redirects needed (internal app, no external links to preserve)

### Claude's Discretion
- File rename: handler files named `player_profile_handler.php` → `member_profile_handler.php`
  and template files named `player_profile.php` → `member_profile.php`
- Variable naming in RLS migration SQL: use clear intermediate steps

</decisions>

<specifics>
## Specific Ideas

- Migration must be a single transaction block in `maybe_migrate_db()` (new migration number, e.g. 029)
- The two `player_id` columns have different semantic roles:
  - `users.player_id` → FK to `players.id` (profile link) — becomes `users.member_id` → FK to `members.id`
  - `cells.player_id` → FK to `users.id` (who owns the cell) — becomes `cells.member_id` → FK to `users.id`
- RLS policies to drop/recreate: cells INSERT, cells UPDATE (both reference `player_id` column in their SQL body)
- `schema.sql` is idempotent: all `CREATE TABLE IF NOT EXISTS players` → `members`, column names updated
- `rls_policies.sql` updated to reference `member_id` instead of `player_id` in policy bodies

</specifics>
