# Phase 8: Player & Club Management - Research

**Researched:** 2026-08-01
**Domain:** PHP / PostgreSQL RLS / EAV schema extension / multi-team session management
**Confidence:** HIGH — findings are derived directly from reading the production codebase

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- `users` table is UNCHANGED except for two nullable FK additions (`player_id`, `phone`)
- `users.team_id` is KEPT and updated at login from `coordinator_teams` (preserves existing RLS pattern)
- All new tables need RLS policies matching the pattern in connection.php
- Multi-team coordinator team-picker must integrate into login flow
- Cross-team stats for players require `set_admin_context()` bypass
- Admin creates/manages clubs, players, attribute groups, player-to-team assignments, and player-to-user links
- `clubs` table: `id`, `name`, `is_active`, `created_at`
- `players` table: `id`, `club_id`, `first_name`, `last_name`, `created_at`, `description`, `phone`, `contact_name`
- `team_memberships`: `id`, `player_id`, `team_id`, `joined_at`, `left_at` (NULL = active)
- `coordinator_teams`: `user_id`, `team_id`, `joined_at`, `left_at` (NULL = active)
- `player_attribute_groups` and `player_attributes` with `visible_to_player`, `editable_by_player` booleans
- `player_attribute_values`: player_id x attribute_id → TEXT value
- Safe idempotent migrations only — `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`, `CREATE TABLE IF NOT EXISTS`

### Claude's Discretion

- Exact UI layout for team-picker (modal vs. page vs. inline select after login)
- Whether coordinator phone is shown in a separate "Koordinatoren-Verzeichnis" page or inline on team detail
- Pagination/filtering on player lists if a club has many players
- Whether club-wide stats use a dedicated stats page or extend the existing member stats view

### Deferred Ideas (OUT OF SCOPE)

- (None explicitly deferred — all ideas from CONTEXT.md are in scope)
</user_constraints>

---

## Summary

Phase 8 introduces a permanent player identity layer (`players`) separate from `users`, clubs that group players across teams, multi-team coordinator support via `coordinator_teams`, and dynamic player attribute EAV. The critical design constraint is that `users.team_id` is kept and kept in sync with the session-active team at all times — this preserves every existing RLS policy without modification.

The biggest implementation complexity is the multi-team coordinator login flow: after credentials are verified with `set_admin_context()`, the handler must query `coordinator_teams`, and if multiple active memberships exist, redirect to a team-picker page before setting session state. Single-team coordinators are entirely unaffected. The `users.team_id` UPDATE on team selection is the mechanism that keeps the existing RLS context working — this must happen on every team switch.

For club-wide cross-team stats, `set_admin_context()` is used for exactly one query, immediately followed by `reset_rls_context()` and `set_team_context()` re-set. This is the same pattern already used in `login_handler.php` for credential lookup. Seven new tables require RLS policies; none require modification of existing policies.

**Primary recommendation:** Implement all seven new tables as a single Migration 012 block in `maybe_migrate_db()`, following the exact pattern of migrations 006 and 009. Populate `coordinator_teams` from `users.team_id` in the same migration.

---

## Standard Stack

No new libraries. Phase 8 is pure PHP + PostgreSQL built on the existing stack.

| Component | Version | Purpose |
|-----------|---------|---------|
| PHP | 8.3+ | All business logic, handlers, templates |
| PostgreSQL | 14+ | Schema, RLS, EAV storage |
| PDO + PDO_PGSQL | built-in | Database access, prepared statements |
| Bootstrap 5.3 CDN | 5.3 | Team-picker UI, player profile card layout |

No additional npm, Composer, or external dependencies.

---

## Architecture Patterns

### New Directory Layout

```
src/
  admin/
    clubs_handler.php               # GET: list clubs
    club_create_handler.php         # GET+POST: create club
    club_action_handler.php         # POST: edit/deactivate/reactivate club
    players_handler.php             # GET: list players (filterable by club/team)
    player_create_handler.php       # GET+POST: create player, assign to club
    player_action_handler.php       # POST: edit, link user account, assign team
    attributes_handler.php          # GET: list attribute groups + attributes
    attribute_group_action_handler.php  # POST: create/edit group
    attribute_action_handler.php    # POST: create/edit attribute
  coordinator/
    players_handler.php             # GET: list players on this team
    player_profile_handler.php      # GET: player detail, attributes, history
    player_attribute_edit_handler.php  # POST: edit attribute values for a player
    select_team_handler.php         # GET: team-picker; POST: set active team
  member/
    player_profile_handler.php      # GET: member's own player profile (if linked)
  templates/
    admin/
      clubs.php
      club_form.php
      players.php
      player_form.php
      attributes.php
    coordinator/
      players.php
      player_profile.php
      select_team.php
    member/
      player_profile.php
```

### Pattern 1: Migration 012 Block

Follow the exact pattern of migrations 006 and 009 in `maybe_migrate_db()`. Gate the whole block on checking whether `clubs` table exists:

```php
// Migration 012: clubs, players, team_memberships, coordinator_teams, player EAV
$clubs_exists = (bool)$pdo->query(
    "SELECT 1 FROM information_schema.tables
     WHERE table_schema = '{$schema}' AND table_name = 'clubs'"
)->fetchColumn();

if (!$clubs_exists) {
    // CREATE clubs, player_attribute_groups, players, team_memberships,
    // coordinator_teams, player_attributes, player_attribute_values
    // ALTER TABLE users ADD COLUMN IF NOT EXISTS player_id ...
    // ALTER TABLE users ADD COLUMN IF NOT EXISTS phone ...
    // Populate coordinator_teams from users WHERE role = 'coordinator' AND team_id IS NOT NULL
    // ENABLE ROW LEVEL SECURITY + policies for all new tables
}
```

Also add the two ALTER TABLE statements unconditionally (idempotent via `IF NOT EXISTS`) outside the `if (!$clubs_exists)` block so they are applied even if the migration is re-run.

### Pattern 2: FK Creation Order (Dependency Chain)

Tables must be created in this exact order to satisfy FK constraints:

```
1. clubs                     (no FK to new tables)
2. player_attribute_groups   (no FK to new tables)
3. players                   (FK → clubs)
4. team_memberships          (FK → players, teams)
5. coordinator_teams         (FK → users, teams)
6. player_attributes         (FK → player_attribute_groups)
7. player_attribute_values   (FK → players, player_attributes)
then:
8. ALTER TABLE users ADD COLUMN IF NOT EXISTS player_id INTEGER REFERENCES players(id) ON DELETE SET NULL
9. ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL
10. INSERT INTO coordinator_teams (user_id, team_id, joined_at)
    SELECT id, team_id, created_at FROM users
    WHERE role = 'coordinator' AND team_id IS NOT NULL
```

Step 10 (populate `coordinator_teams`) must happen BEFORE RLS is enabled on `coordinator_teams`, because there is no team context at migration time.

### Pattern 3: Multi-Team Coordinator Login Flow

The change is confined to `src/auth/login_handler.php` and a new `select_team_handler.php`.

**Modified login flow (after password_verify succeeds, within the existing `set_admin_context()` window):**

```php
// After existing user credential lookup (already using admin context):
// 1. Query coordinator_teams for active memberships
$ct_stmt = $pdo->prepare(
    "SELECT ct.team_id, t.name AS team_name
     FROM coordinator_teams ct
     JOIN teams t ON t.id = ct.team_id
     WHERE ct.user_id = ? AND ct.left_at IS NULL AND t.is_active = TRUE
     ORDER BY ct.joined_at DESC"
);
$ct_stmt->execute([$user['id']]);
$active_teams = $ct_stmt->fetchAll();

// 2. Single team OR member: use existing flow (users.team_id already correct)
if ($user['role'] !== 'coordinator' || count($active_teams) <= 1) {
    // existing session setup unchanged
    // ...
    redirect('/coordinator/members');
}

// 3. Multiple active teams: store pending state in session, redirect to picker
session_regenerate_id(true);
$_SESSION['user_id']              = $user['id'];
$_SESSION['role']                 = 'coordinator';
$_SESSION['display_name']         = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['last_activity']        = time();
$_SESSION['pending_team_pick']    = true;  // guards other coordinator routes
$_SESSION['available_teams']      = $active_teams; // for picker display
reset_rls_context($pdo);
redirect('/coordinator/select-team');
```

**New `select_team_handler.php` (GET+POST):**

```php
// Guard: must have pending_team_pick in session
if (empty($_SESSION['pending_team_pick']) && empty($_SESSION['user_id'])) {
    redirect('/login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $team_id = (int)($_POST['team_id'] ?? 0);
    // Validate team_id is in $_SESSION['available_teams']
    $valid = array_filter($_SESSION['available_teams'], fn($t) => $t['team_id'] === $team_id);
    if (!$valid) redirect('/coordinator/select-team?error=1');

    $pdo = get_db();
    set_admin_context($pdo);
    // Keep users.team_id in sync with active session team
    $pdo->prepare("UPDATE users SET team_id = ? WHERE id = ?")->execute([$team_id, $_SESSION['user_id']]);
    $team_name = current($valid)['team_name'];
    reset_rls_context($pdo);

    $_SESSION['team_id']       = $team_id;
    $_SESSION['team_name']     = $team_name;
    unset($_SESSION['pending_team_pick']);
    unset($_SESSION['available_teams']);
    set_team_context($pdo, $team_id, 'coordinator', $_SESSION['user_id']);
    redirect('/coordinator/members');
}
// GET: render team-picker using $_SESSION['available_teams']
```

**Team-switch without logout (coordinator already logged in):**
- New route `/coordinator/switch-team` — same as `/coordinator/select-team` but triggered from nav
- After POST, redirects back to `/coordinator/members`

**Guard in `require_coordinator()`:** If `$_SESSION['pending_team_pick']` is set, redirect to `/coordinator/select-team`. This prevents accessing coordinator pages before completing team selection.

### Pattern 4: Cross-Team Stats Query (Admin Context Bypass)

Used by coordinator (player profile page) and member (own stats across teams):

```php
// In coordinator/player_profile_handler.php after require_coordinator():
$pdo = get_db();

// 1. Find linked user account for this player
$user_stmt = $pdo->prepare(
    "SELECT id FROM users WHERE player_id = ? LIMIT 1"
);
$user_stmt->execute([$player_id]);
$linked_user = $user_stmt->fetchColumn();

if ($linked_user) {
    // 2. Briefly bypass RLS to query cross-team cells
    set_admin_context($pdo);
    $stats_stmt = $pdo->prepare(
        "SELECT c.name AS col_name, c.data_type, t.name AS team_name, l.date,
                ce.value
         FROM cells ce
         JOIN lists l ON l.id = ce.list_id
         JOIN teams t ON t.id = l.team_id
         JOIN columns c ON c.id = ce.column_id AND c.list_id IS NULL
         WHERE ce.player_id = :user_id
         ORDER BY l.date DESC"
    );
    $stats_stmt->execute([':user_id' => $linked_user]);
    $cross_stats = $stats_stmt->fetchAll();

    // 3. Immediately restore team context
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], 'coordinator', (int)$_SESSION['user_id']);
}
```

**Critical:** `reset_rls_context()` + `set_team_context()` must be called before any other query runs. The PDO connection is a request-scoped singleton — the admin bypass window must be minimal.

### Pattern 5: Player EAV vs. List EAV

The existing `columns` + `cells` EAV and the new `player_attributes` + `player_attribute_values` EAV differ in important ways:

| Dimension | List EAV (`columns` / `cells`) | Player EAV (`player_attributes` / `player_attribute_values`) |
|-----------|-------------------------------|--------------------------------------------------------------|
| Structure owner | Coordinator (team-scoped) | Admin (global, shared across all teams) |
| Scope of structure | Per-team, optionally per-list | Global — no team_id or list_id |
| Value record key | (list_id, column_id, player_id) | (player_id, attribute_id) |
| Visibility control | List-level (public/protected/private) | Attribute-level (`visible_to_player` boolean) |
| Edit control | List-level + role | Attribute-level (`editable_by_player` boolean) |
| Grouping | None (flat) | `player_attribute_groups` with `sort_order` |
| Data types | boolean, number, text | TEXT only (single type) |
| Stats aggregation | Yes (global columns only) | No — descriptive only |

**Key implication for planner:** Player attribute values are simpler to query (no list join needed) but the permission check is per-attribute, not per-list.

### Pattern 6: RLS for New Tables

All new tables use `ENABLE ROW LEVEL SECURITY` + `FORCE ROW LEVEL SECURITY` + `DROP POLICY IF EXISTS ... CREATE POLICY` (drop-and-recreate for idempotency), matching the pattern in `db_init_rls()`.

**`clubs`**
```sql
-- SELECT: any logged-in user (coordinator/member needs to see club names on player profiles)
-- Admin bypass covers admin reads
CREATE POLICY clubs_select ON clubs FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
);
CREATE POLICY clubs_insert ON clubs FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY clubs_update ON clubs FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY clubs_delete ON clubs FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);
```

**`players`** (no direct team_id — scope via team_memberships subquery)
```sql
-- Coordinator sees players currently on their team
-- Member sees their own linked player only
-- Admin sees all
CREATE POLICY players_select ON players FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR EXISTS (
        SELECT 1 FROM team_memberships tm
        WHERE tm.player_id = players.id
          AND tm.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
          AND tm.left_at IS NULL
    )
    OR EXISTS (
        SELECT 1 FROM users u
        WHERE u.player_id = players.id
          AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
    )
);
CREATE POLICY players_insert ON players FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY players_update ON players FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
```

**`team_memberships`** (team-scoped, admin-only writes)
```sql
CREATE POLICY tm_select ON team_memberships FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
);
CREATE POLICY tm_insert ON team_memberships FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY tm_update ON team_memberships FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
```

**`coordinator_teams`** (coordinator sees own rows; admin sees all)
```sql
CREATE POLICY ct_select ON coordinator_teams FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
);
CREATE POLICY ct_insert ON coordinator_teams FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY ct_update ON coordinator_teams FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
```

**`player_attribute_groups`** (global; any logged-in user reads; admin writes)
```sql
CREATE POLICY pag_select ON player_attribute_groups FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
);
CREATE POLICY pag_insert ON player_attribute_groups FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY pag_update ON player_attribute_groups FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY pag_delete ON player_attribute_groups FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);
```

**`player_attributes`** (member reads only if `visible_to_player = TRUE`)
```sql
CREATE POLICY pa_select ON player_attributes FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (
        current_setting('app.current_role', true) = 'member'
        AND visible_to_player = TRUE
    )
);
CREATE POLICY pa_insert ON player_attributes FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY pa_update ON player_attributes FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
);
CREATE POLICY pa_delete ON player_attributes FOR DELETE USING (
    current_setting('app.is_admin', true) = 'true'
);
```

**`player_attribute_values`** (most complex — coordinator scoped to current team's players)
```sql
-- Coordinator sees values for players on their team
-- Member sees own player's values for visible attributes
-- Admin sees all
CREATE POLICY pav_select ON player_attribute_values FOR SELECT USING (
    current_setting('app.is_admin', true) = 'true'
    OR (
        current_setting('app.current_role', true) = 'coordinator'
        AND EXISTS (
            SELECT 1 FROM team_memberships tm
            WHERE tm.player_id = player_attribute_values.player_id
              AND tm.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
              AND tm.left_at IS NULL
        )
    )
    OR (
        current_setting('app.current_role', true) = 'member'
        AND EXISTS (
            SELECT 1 FROM users u
            WHERE u.player_id = player_attribute_values.player_id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
        AND EXISTS (
            SELECT 1 FROM player_attributes pa
            WHERE pa.id = player_attribute_values.attribute_id
              AND pa.visible_to_player = TRUE
        )
    )
);
CREATE POLICY pav_insert ON player_attribute_values FOR INSERT WITH CHECK (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (
        current_setting('app.current_role', true) = 'member'
        AND EXISTS (
            SELECT 1 FROM users u
            WHERE u.player_id = player_attribute_values.player_id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
        AND EXISTS (
            SELECT 1 FROM player_attributes pa
            WHERE pa.id = player_attribute_values.attribute_id
              AND pa.editable_by_player = TRUE
        )
    )
);
CREATE POLICY pav_update ON player_attribute_values FOR UPDATE USING (
    current_setting('app.is_admin', true) = 'true'
    OR current_setting('app.current_role', true) = 'coordinator'
    OR (
        current_setting('app.current_role', true) = 'member'
        AND EXISTS (
            SELECT 1 FROM users u
            WHERE u.player_id = player_attribute_values.player_id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
        AND EXISTS (
            SELECT 1 FROM player_attributes pa
            WHERE pa.id = player_attribute_values.attribute_id
              AND pa.editable_by_player = TRUE
        )
    )
);
```

### Pattern 7: Route Additions in `public/index.php`

New routes to add (following existing `match(true)` pattern):

```
/coordinator/select-team        → src/coordinator/select_team_handler.php
/coordinator/switch-team        → src/coordinator/select_team_handler.php (same handler, different flow)
/coordinator/players            → src/coordinator/players_handler.php
/coordinator/players/{id}       → src/coordinator/player_profile_handler.php
/coordinator/players/{id}/attributes/save → src/coordinator/player_attribute_edit_handler.php

/admin/clubs                    → src/admin/clubs_handler.php
/admin/clubs/create             → src/admin/club_create_handler.php
/admin/clubs/{id}/(edit|deactivate|reactivate) → src/admin/club_action_handler.php
/admin/players                  → src/admin/players_handler.php
/admin/players/create           → src/admin/player_create_handler.php
/admin/players/{id}/(edit|link-user|assign-team) → src/admin/player_action_handler.php
/admin/attributes               → src/admin/attributes_handler.php
/admin/attributes/groups/create → src/admin/attribute_group_action_handler.php
/admin/attributes/{group_id}/attributes/create → src/admin/attribute_action_handler.php

/member/player-profile          → src/member/player_profile_handler.php
```

### Pattern 8: Coordinator Creation Must Also Insert coordinator_teams Row

The existing `coordinator_create_handler.php` inserts into `users` only. After Phase 8 it must also insert a `coordinator_teams` row:

```php
// After INSERT INTO users ...:
$new_user_id = (int)$pdo->lastInsertId();
$ct_stmt = $pdo->prepare(
    "INSERT INTO coordinator_teams (user_id, team_id, joined_at)
     VALUES (?, ?, NOW())"
);
$ct_stmt->execute([$new_user_id, $team_id]);
```

### Pattern 9: Team Deletion Must Not Delete Multi-Team Coordinators

Current `team_action_handler.php` for `action = 'delete'`:
```php
$pdo->prepare("DELETE FROM users WHERE team_id = ?")->execute([$team_id]);
$pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$team_id]);
```

After Phase 8, `coordinator_teams` has `ON DELETE CASCADE` on `team_id`, so team deletion automatically removes those coordinator_teams rows. But the explicit `DELETE FROM users WHERE team_id = ?` would delete coordinators who may have other active team memberships.

**Fix required in `team_action_handler.php`:** Remove or scope the user deletion. Only delete `users` records where the user has NO other active `coordinator_teams` rows pointing to a different team:

```php
// Delete users who belong ONLY to this team (no other coordinator_teams rows)
$pdo->prepare(
    "DELETE FROM users WHERE team_id = ?
     AND NOT EXISTS (
         SELECT 1 FROM coordinator_teams
         WHERE coordinator_teams.user_id = users.id
           AND coordinator_teams.team_id != ?
           AND coordinator_teams.left_at IS NULL
     )"
)->execute([$team_id, $team_id]);
// Teams CASCADE handles: lists, columns, cells, files, tickers, ticker_tags, team_memberships, coordinator_teams
$pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$team_id]);
```

### Anti-Patterns to Avoid

- **Do not add `team_id` to `players` table.** Players are scoped to teams via `team_memberships`. Adding a direct FK would break the multi-team history model.
- **Do not store `coordinator_teams` lookups in a long-lived session var.** Query from `coordinator_teams` fresh on each team-switch. Only `$_SESSION['team_id']` and `$_SESSION['team_name']` are session-persisted.
- **Do not call `set_admin_context()` without immediately calling `reset_rls_context()` + `set_team_context()` afterwards.** The PDO connection is a singleton — a stale admin context leaks to subsequent queries.
- **Do not use the existing `users` RLS to scope player lists.** Coordinators see `users` rows with `team_id = current_team_id`, which gives them only the member accounts on their team. For the new player-based listing, query `players` JOIN `team_memberships` directly.
- **Do not add `pending_team_pick` check inside `require_coordinator()` without testing single-team coordinators.** Must be a pure additive guard — if `pending_team_pick` is absent (normal case), behavior is identical to today.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Unique partial index for active coordinator_team | Manual PHP check | `CREATE UNIQUE INDEX ... WHERE left_at IS NULL` | DB enforces one active membership per (user, team) pair; PHP check is race-prone |
| ON CONFLICT UPSERT for player_attribute_values | SELECT then INSERT/UPDATE logic | `INSERT ... ON CONFLICT (player_id, attribute_id) DO UPDATE SET value = EXCLUDED.value` | Existing `cells` table uses UNIQUE on (list_id, column_id, player_id) for the same pattern |
| Cross-team stats aggregation | PHP loop over per-team connections | Single SQL query with `set_admin_context()` bypass | Admin context bypasses RLS cleanly; separate connections add complexity |
| Phone number format validation | Custom regex | `filter_var($phone, FILTER_SANITIZE_NUMBER_INT)` or store raw | Store exactly what user types (VARCHAR 50); no strict format required |

---

## Common Pitfalls

### Pitfall 1: Admin Context Leaking to Subsequent Queries

**What goes wrong:** `set_admin_context()` is called for a cross-team query. The handler returns before calling `reset_rls_context()`. Subsequent queries on the same request bypass team isolation.

**Why it happens:** PDO is a request-scoped singleton. The GUC `app.is_admin = 'true'` persists for the connection lifetime until explicitly reset.

**How to avoid:** Always use the three-call pattern: `set_admin_context()` → query → `reset_rls_context()` → `set_team_context()`. Never return early from a function that opened an admin context.

**Warning signs:** Coordinator sees data from other teams unexpectedly; admin context check in RLS always returns true mid-request.

### Pitfall 2: coordinator_teams Population Missed for Existing Coordinators

**What goes wrong:** Migration creates `coordinator_teams` but doesn't populate rows from existing `users.team_id`. Existing coordinators get the multi-team picker with zero options and cannot log in.

**Why it happens:** The CREATE TABLE migration doesn't auto-populate.

**How to avoid:** In Migration 012, after creating `coordinator_teams`, run:
```sql
INSERT INTO coordinator_teams (user_id, team_id, joined_at)
SELECT id, team_id, created_at FROM users
WHERE role = 'coordinator' AND team_id IS NOT NULL
ON CONFLICT DO NOTHING
```
This runs in the same `if (!$clubs_exists)` block, before RLS is enabled on the table.

### Pitfall 3: `pending_team_pick` Guard Missing from `require_coordinator()`

**What goes wrong:** A multi-team coordinator hits `/coordinator/members` directly after login (before selecting a team). `$_SESSION['team_id']` is not set, causing `set_team_context()` to receive 0 as team_id, breaking all RLS queries.

**Why it happens:** The existing `require_coordinator()` reads `$_SESSION['team_id']` without checking if team selection is pending.

**How to avoid:** Add at the top of `require_coordinator()`:
```php
if (!empty($_SESSION['pending_team_pick'])) {
    redirect('/coordinator/select-team');
}
```

### Pitfall 4: `players` Table RLS Policy Returning Zero Rows for Valid Access

**What goes wrong:** Coordinator queries players on their team and gets an empty result, even though `team_memberships` has active rows. The `EXISTS` subquery in the RLS policy uses `NULLIF(..., '')::integer` — if `app.current_team_id` is empty string (context not set), the cast to integer fails silently.

**Why it happens:** `NULLIF('', '')::integer` returns NULL; `players.id = NULL` is always false. If `set_team_context()` wasn't called (e.g., `require_coordinator()` hadn't run yet), the policy filters everything out.

**How to avoid:** Same pattern as existing tables — `require_coordinator()` must be the first call in every coordinator handler, which calls `set_team_context()` before any query. No query should run before `require_coordinator()`.

### Pitfall 5: Team Deletion Destroying Multi-Team Coordinators

**What goes wrong:** Admin deletes Team A. The existing handler does `DELETE FROM users WHERE team_id = ?`. A coordinator who manages both Team A and Team B is deleted from the database, even though their Team B membership is still valid.

**Why it happens:** The original deletion logic predates multi-team coordinators.

**How to avoid:** Update `team_action_handler.php` to scope user deletion to users who ONLY belong to this team. See Pattern 9 above.

### Pitfall 6: `users.team_id` Diverging from Active Session

**What goes wrong:** A coordinator switches teams in the UI (via `/coordinator/switch-team`). `$_SESSION['team_id']` is updated but `users.team_id` in the DB is not. On next request, `require_coordinator()` calls `set_team_context($pdo, (int)$_SESSION['team_id'], ...)` so the session is correct, but the DB row still shows the old team. If any code reads `users.team_id` directly (e.g., `teams_handler.php` showing which coordinators are in which team), it shows stale data.

**How to avoid:** Every team-switch (team-picker POST) must UPDATE `users.team_id` in the DB. This is defined as a locked decision. Always update both the DB row and the session variable atomically.

---

## Code Examples

### Full Schema for New Tables (db_init_schema addition)

```php
// Migration 012: add to db_init_schema() for fresh installs
$pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.clubs (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    is_active  BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.player_attribute_groups (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.players (
    id           SERIAL PRIMARY KEY,
    club_id      INTEGER REFERENCES {$s}.clubs(id) ON DELETE SET NULL,
    first_name   VARCHAR(100) NOT NULL,
    last_name    VARCHAR(100) NOT NULL,
    description  TEXT NULL,
    phone        VARCHAR(50) NULL,
    contact_name VARCHAR(100) NULL,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_players_club ON {$s}.players(club_id)");

$pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.team_memberships (
    id        SERIAL PRIMARY KEY,
    player_id INTEGER NOT NULL REFERENCES {$s}.players(id) ON DELETE CASCADE,
    team_id   INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    left_at   TIMESTAMPTZ NULL
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tm_player ON {$s}.team_memberships(player_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tm_team ON {$s}.team_memberships(team_id)");
$pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_tm_active ON {$s}.team_memberships(player_id) WHERE left_at IS NULL");

$pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.coordinator_teams (
    id        SERIAL PRIMARY KEY,
    user_id   INTEGER NOT NULL REFERENCES {$s}.users(id) ON DELETE CASCADE,
    team_id   INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    left_at   TIMESTAMPTZ NULL
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ct_user ON {$s}.coordinator_teams(user_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ct_team ON {$s}.coordinator_teams(team_id)");
$pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_ct_active ON {$s}.coordinator_teams(user_id, team_id) WHERE left_at IS NULL");

$pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.player_attributes (
    id                 SERIAL PRIMARY KEY,
    group_id           INTEGER NOT NULL REFERENCES {$s}.player_attribute_groups(id) ON DELETE CASCADE,
    name               VARCHAR(100) NOT NULL,
    visible_to_player  BOOLEAN NOT NULL DEFAULT TRUE,
    editable_by_player BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order         INTEGER NOT NULL DEFAULT 0,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_pa_group ON {$s}.player_attributes(group_id)");

$pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.player_attribute_values (
    id           SERIAL PRIMARY KEY,
    player_id    INTEGER NOT NULL REFERENCES {$s}.players(id) ON DELETE CASCADE,
    attribute_id INTEGER NOT NULL REFERENCES {$s}.player_attributes(id) ON DELETE CASCADE,
    value        TEXT NOT NULL DEFAULT '',
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (player_id, attribute_id)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_pav_player ON {$s}.player_attribute_values(player_id)");

// ALTER TABLE users — always outside the clubs_exists gate (idempotent)
$pdo->exec("ALTER TABLE {$s}.users ADD COLUMN IF NOT EXISTS player_id INTEGER REFERENCES {$s}.players(id) ON DELETE SET NULL");
$pdo->exec("ALTER TABLE {$s}.users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL");
```

### Populate coordinator_teams from Existing Data

```php
// Run BEFORE enabling RLS on coordinator_teams
$pdo->exec(
    "INSERT INTO {$schema}.coordinator_teams (user_id, team_id, joined_at)
     SELECT id, team_id, created_at FROM {$schema}.users
     WHERE role = 'coordinator' AND team_id IS NOT NULL
     ON CONFLICT DO NOTHING"
);
```

### Player Attribute Value Upsert (coordinator edit)

```php
// Source: same pattern as cells UNIQUE constraint approach
$stmt = $pdo->prepare(
    "INSERT INTO player_attribute_values (player_id, attribute_id, value, updated_at)
     VALUES (?, ?, ?, NOW())
     ON CONFLICT (player_id, attribute_id)
     DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
);
$stmt->execute([$player_id, $attribute_id, $value]);
```

### Querying Player Attributes for Profile Display

```php
// Source: mirrors list_detail_handler.php EAV pattern, but no list join
$stmt = $pdo->prepare(
    "SELECT pag.name AS group_name, pag.sort_order AS group_order,
            pa.id AS attr_id, pa.name AS attr_name, pa.sort_order AS attr_order,
            pa.visible_to_player, pa.editable_by_player,
            COALESCE(pav.value, '') AS value
     FROM player_attribute_groups pag
     JOIN player_attributes pa ON pa.group_id = pag.id
     LEFT JOIN player_attribute_values pav
            ON pav.attribute_id = pa.id AND pav.player_id = ?
     WHERE pa.visible_to_player = TRUE  -- remove this condition for coordinator view
     ORDER BY pag.sort_order, pag.name, pa.sort_order, pa.name"
);
$stmt->execute([$player_id]);
```

---

## Open Questions

1. **Player linked to multiple user accounts across time**
   - What we know: `users.player_id` is a nullable FK. One player → at most one linked user at a time.
   - What's unclear: If the same physical person had two `users` accounts over their history (rare), cross-team stats would only show the account linked NOW. Historical cell data from the "old" account would be unreachable via the player FK.
   - Recommendation: Treat this as out-of-scope edge case. Document that `users.player_id` is the canonical link; historical data from unlinked accounts is not aggregated.

2. **Admin coordinator assignment to additional teams**
   - What we know: `coordinator_create_handler.php` links a coordinator to exactly one team on creation.
   - What's unclear: How does admin add a coordinator to a SECOND team? Is there a separate "assign coordinator to team" action, or does admin edit the coordinator record?
   - Recommendation: Add a dedicated action on the coordinator admin page: "Weiteres Team zuweisen" → SELECT from active teams → INSERT coordinator_teams row. No UI designed yet — planner must define this page/action.

3. **`team_memberships` unique constraint on active membership**
   - What we know: CONTEXT.md says "A player has exactly one active team membership at any time."
   - What's unclear: Should the DB enforce this with a partial unique index `WHERE left_at IS NULL`, or trust the application layer?
   - Recommendation: DB-level partial unique index (shown in code examples). This prevents duplicate active memberships even on admin form double-submits.

4. **Admin `players` listing scope**
   - What we know: Admin manages all players globally. There could be many players across all clubs.
   - What's unclear: Should the admin players list be filterable by club, team, or both? No pagination decision made.
   - Recommendation: Default to filter-by-club (dropdown). Add filter-by-team as secondary. No pagination needed unless a club exceeds ~200 players — flag if volume grows.

---

## Sources

### Primary (HIGH confidence)

All findings are derived directly from reading the production source code files. No external sources consulted — the codebase is authoritative for this research.

- `database/schema.sql` — Full current schema; baseline for all additive changes
- `database/rls_policies.sql` — RLS policy patterns replicated for all new tables
- `src/db/connection.php` — `maybe_migrate_db()` pattern, `set_team_context()`, `set_admin_context()`, `reset_rls_context()` — all migration and context patterns
- `src/auth/login_handler.php` — Multi-team extension point; existing admin context bypass pattern
- `src/auth/session.php` — `require_coordinator()`, `require_member()`, `require_admin()` — guard functions and their session assumptions
- `src/admin/teams_handler.php` + `src/admin/team_action_handler.php` — Admin CRUD pattern for new admin pages
- `src/admin/coordinator_create_handler.php` — Coordinator creation; must be extended to also insert `coordinator_teams` row
- `public/index.php` — Route dispatch pattern for all new routes
- `.planning/phases/08-player-club-management/08-CONTEXT.md` — Locked decisions and constraints

### Secondary (MEDIUM confidence)

- PostgreSQL partial unique index (`WHERE left_at IS NULL`) — standard PostgreSQL feature, well documented; HIGH confidence it works on PostgreSQL 14+

---

## Metadata

**Confidence breakdown:**
- Migration order and FK dependencies: HIGH — derived directly from schema structure
- RLS policy design: HIGH — replicated from established patterns in codebase
- Login flow extension: HIGH — login handler read in full; extension points are clear
- Cross-team stats pattern: HIGH — `set_admin_context()` + `reset_rls_context()` pattern already exists in login handler
- Team deletion fix: HIGH — existing handler code read; multi-team coordinator deletion risk is clear and documented
- Player EAV vs List EAV differences: HIGH — both EAV implementations read from schema and handlers
- Admin UI structure: MEDIUM — handler patterns are clear; exact template layout left to Claude's Discretion

**Research date:** 2026-08-01
**Valid until:** 2026-09-01 (stable stack; only invalidated by further schema changes before Phase 8 starts)
