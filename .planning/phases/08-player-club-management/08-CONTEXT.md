# Phase 8: Player & Club Management - Context

**Gathered:** 2026-08-01
**Status:** Ready for planning
**Source:** Conversation-derived (decisions locked from design discussion)

<domain>
## Phase Boundary

Introduce a permanent player identity layer separate from `users`. Players belong to clubs; clubs group players across teams. Coordinators gain multi-team support via a join table. Player profiles get dynamic admin-defined attribute groups. Club-wide statistics become available. All schema changes are safe idempotent migrations — the app is already in production.

</domain>

<decisions>
## Implementation Decisions

### `users` Table — Unchanged
- `users` always have an app login. The table, auth flow, sessions, roles, and all existing list/cell/ticker logic remain untouched.
- The only additive change: nullable `player_id` FK on `users` linking to the new `players` table.

### `clubs` Table — New
- Admin creates and manages clubs.
- A club is the permanent home of players, independent of which team they're on.
- Fields: `id`, `name`, `is_active`, `created_at`.

### `players` Table — New Permanent Identity
- Separate from `users`. A player is a roster record that persists across team assignments and years.
- Fields: `id`, `club_id`, `first_name`, `last_name`, `created_at`, plus nullable `description`, `phone`, `contact_name`.
- A player may or may not have a linked `users` account — this is an admin decision, completely independent of app usage. The link is `users.player_id` (nullable FK).
- A player is assigned to one team at a time (`team_memberships` join table tracks history).

### `team_memberships` Table — New (Players)
- Tracks which team a player is on, with full history.
- Fields: `id`, `player_id`, `team_id`, `joined_at`, `left_at` (NULL = currently active).
- A player has exactly one active team membership at any time.

### Dynamic Player Attributes — EAV (Admin-Defined)
- Admin defines `player_attribute_groups` (e.g. "Kontakt", "Spielerprofil") and `player_attributes` within each group.
- Each attribute has:
  - `visible_to_player` (boolean) — whether the logged-in player can see this attribute's value
  - `editable_by_player` (boolean) — whether the player can edit the value themselves
  - Coordinator (and admin) can always see and edit all attributes.
- Values stored in `player_attribute_values` (player_id × attribute_id → value TEXT).

### Multi-Team Coordinators
- Replace single `users.team_id` for coordinators with a `coordinator_teams` join table.
- Fields: `user_id`, `team_id`, `joined_at`, `left_at` (NULL = currently active).
- When a coordinator moves to a new team: `left_at` set on old row, new row inserted. History preserved, same `users` record throughout.
- Multi-team session: if a coordinator manages multiple active teams, a team-picker appears after login. Session `team_id` is set to the selected team. They can switch without logging out.

### Coordinator Extended Profile
- Add `phone` column to `users` (nullable VARCHAR, safe migration).
- Coordinators can see other active coordinators' name and phone within their team scope.

### Club-Wide Statistics
- A player with a linked `users` account can see aggregated stats across ALL their team memberships (not just their current team).
- A coordinator can open a player profile and see full team assignment history and overall stats.
- Cross-team stat queries require bypassing team-scoped RLS — handled via a dedicated query path using `set_admin_context()` scoped to the club.

### Admin Scope
- One global admin (unchanged). Admin can:
  - Create and manage clubs
  - Create players and assign them to clubs
  - Assign players to teams (create team_memberships rows)
  - Link a player to a `users` account (set `users.player_id`)
  - Create `users` accounts as today (no change to existing flow)

### Safe Migrations — All Changes
- `ALTER TABLE users ADD COLUMN IF NOT EXISTS player_id INTEGER REFERENCES players(id) ON DELETE SET NULL`
- `ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL`
- All new tables use `CREATE TABLE IF NOT EXISTS`
- `users.team_id` for coordinators: kept as session-active team (populated at login from `coordinator_teams`); NOT removed to avoid breaking existing RLS context pattern.

### Claude's Discretion
- Exact UI layout for team-picker (modal vs. page vs. inline select after login)
- Whether coordinator phone is shown in a separate "Koordinatoren-Verzeichnis" page or inline on team detail
- Pagination/filtering on player lists if a club has many players
- Whether club-wide stats use a dedicated stats page or extend the existing member stats view

</decisions>

<specifics>
## Specific Ideas

- Team-picker after login for multi-team coordinators: show team name + role badge, one tap to enter. Can be a simple full-page select if only 2–3 teams, or a card list.
- Player profile page (coordinator view): name, club, current team, assignment history timeline, attribute groups with values, stats summary.
- Attribute groups displayed as collapsible sections on the player profile.
- `coordinator_teams` replaces coordinator's `users.team_id` conceptually but `users.team_id` is kept and updated at login to preserve existing RLS `set_team_context()` behavior.

</specifics>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing Schema & Migrations
- `database/schema.sql` — Full current schema; all migrations must be additive on top of this
- `src/db/connection.php` — `db_init_schema()`, `db_migrate_schema()`, `set_team_context()`, `set_admin_context()`

### Auth & Session Pattern
- `src/utils/helpers.php` — `require_coordinator()`, `require_member()`, `require_admin()`, session structure
- `src/auth/login_handler.php` — Login flow; team switcher must integrate here

### Admin Patterns (extend these)
- `src/admin/teams_handler.php` + `src/templates/admin/dashboard.php` — Existing admin UI patterns
- `src/admin/team_action_handler.php` — Existing admin action handler pattern
- `src/admin/coordinator_create_handler.php` — Coordinator creation pattern

### EAV Reference Implementation
- `database/schema.sql` — `columns` + `cells` tables as EAV reference for player attributes
- `src/coordinator/list_detail_handler.php` — How EAV values are read/written

### Routing
- `public/index.php` — Front controller; all new routes added here

### RLS & Team Context
- `database/rls_policies.sql` — RLS policies; new tables need policies
- `src/db/connection.php` → `set_admin_context()` — Used for cross-team queries

</canonical_refs>
