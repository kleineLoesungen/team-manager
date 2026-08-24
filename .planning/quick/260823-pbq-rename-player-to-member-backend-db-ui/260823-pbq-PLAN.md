---
phase: quick
plan: 260823-pbq
type: execute
wave: 1
depends_on: []
files_modified:
  - src/db/connection.php
  - database/schema.sql
  - database/rls_policies.sql
  - src/auth/session.php
  - src/utils/helpers.php
  - src/templates/member/layout.php
  - src/templates/member/player_profile.php
  - src/templates/member/member_profile.php
  - src/templates/coordinator/player_profile.php
  - src/templates/coordinator/member_profile.php
  - src/member/player_profile_handler.php
  - src/member/member_profile_handler.php
  - src/coordinator/player_profile_handler.php
  - src/coordinator/member_profile_handler.php
  - public/index.php
  - src/coordinator/members_handler.php
  - src/coordinator/member_edit_email_handler.php
  - src/coordinator/member_change_player_handler.php
  - src/coordinator/member_create_handler.php
  - src/coordinator/player_edit_handler.php
  - src/coordinator/player_attribute_edit_handler.php
  - src/coordinator/player_link_handler.php
  - src/coordinator/players_handler.php
  - src/coordinator/list_row_edit_handler.php
  - src/coordinator/list_detail_handler.php
  - src/coordinator/list_create_handler.php
  - src/coordinator/list_settings_handler.php
  - src/coordinator/stats_handler.php
  - src/coordinator/list_notify_handler.php
  - src/coordinator/file_notify_handler.php
  - src/coordinator/ticker_create_handler.php
  - src/coordinator/ticker_detail_handler.php
  - src/admin/players_handler.php
  - src/admin/player_create_handler.php
  - src/admin/player_edit_handler.php
  - src/admin/player_action_handler.php
  - src/member/player_profile_handler.php
  - src/member/list_detail_handler.php
  - src/member/list_row_edit_handler.php
  - src/member/stats_handler.php
  - src/member/profile_attributes_handler.php
  - src/templates/coordinator/members.php
  - src/templates/coordinator/member_form.php
  - src/templates/coordinator/member_change_player.php
  - src/templates/coordinator/players.php
  - src/templates/coordinator/player_edit.php
  - src/templates/coordinator/list_row_form.php
  - src/templates/member/profile.php
  - src/templates/admin/attributes.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "App boots without errors after DB migration"
    - "Member can access /member/member-profile (old /member/player-profile now redirects or is renamed)"
    - "Coordinator can access player (now member) profiles at their existing URLs"
    - "Cells can be read and written by members (RLS enforces member_id column)"
    - "Member attribute values are accessible (RLS on member_attribute_values with member_id)"
    - "Admin player management still works (SQL queries updated to use members table)"
  artifacts:
    - path: "src/db/connection.php"
      provides: "Migration 029 + updated db_init_schema/db_init_rls"
      contains: "migration 029"
    - path: "database/schema.sql"
      provides: "Updated schema with members, member_attribute_* tables"
      contains: "member_id"
    - path: "database/rls_policies.sql"
      provides: "Updated RLS policies with member_id column"
      contains: "member_id"
    - path: "src/member/member_profile_handler.php"
      provides: "Renamed member profile handler"
    - path: "src/coordinator/member_profile_handler.php"
      provides: "Renamed coordinator member profile handler"
  key_links:
    - from: "cells INSERT/UPDATE policies"
      to: "cells.member_id column"
      via: "migration 029 policy recreation"
      pattern: "member_id = NULLIF.*current_user_id"
    - from: "member_attribute_values SELECT policy"
      to: "users.member_id column"
      via: "migration 029 mav_select"
      pattern: "u.member_id = member_attribute_values.member_id"
    - from: "public/index.php"
      to: "src/member/member_profile_handler.php"
      via: "/member/member-profile route"
      pattern: "member-profile.*member_profile_handler"
---

<objective>
Rename all "player" terminology to "member" across the full stack: DB tables/columns, PHP code,
URL routes, and UI text. This aligns the codebase with the already-established "Mitglied"
(member) language used in the UI and role system.

Purpose: Eliminate the last "player" / "Spieler" vocabulary from code while keeping all
functionality intact.

Output:
- Migration 029 in `maybe_migrate_db()` renames tables and columns, recreates RLS policies
- `db_init_schema()` and `db_init_rls()` updated for fresh installs
- `database/schema.sql` and `rls_policies.sql` updated
- 4 PHP files renamed (git mv)
- All SQL in handlers uses `members`, `member_id`, `member_attribute_*`
- URL `/member/player-profile` → `/member/member-profile`
- `render_player_page()` → `render_member_page()`, `require_player()` becomes a shim
- UI text "Spieler" → "Mitglied" throughout templates
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/quick/260823-pbq-rename-player-to-member-backend-db-ui/260823-pbq-CONTEXT.md

Key architecture facts:
- `maybe_migrate_db()` in `src/db/connection.php` runs on every HTTP request (idempotent)
- Last migration is 028; next is 029
- `cells.player_id` — NO foreign key to users (dropped in migration 006); stores user_id for member lists OR free_list_rows.id for free lists
- `users.player_id` — FK to `players.id`; NOT NULL after migration 024
- `player_attribute_values.player_id` — FK to `players.id`
- `players` RLS: guarded via `users.player_id` join (will become `users.member_id`)
- `cells_insert` and `cells_ownership_update` policies reference `player_id` column by name — MUST be dropped and recreated after column rename
- The coordinator's player profile URL is `/coordinator/players/{id}` (not /coordinator/player-profile); only the file is renamed, URL namespace stays
- `player_id_link` is a form field name in member_form.php and member_change_player.php — rename to `member_id_link`, update handlers that read `$_POST['player_id_link']`
</context>

<tasks>

<task type="auto">
  <name>Task 1: DB Migration 029 + db_init_schema/db_init_rls + schema files</name>
  <files>
    src/db/connection.php,
    database/schema.sql,
    database/rls_policies.sql
  </files>
  <action>
Add Migration 029 at the END of `maybe_migrate_db()` (after migration 028 block, before the closing brace):

```php
// Migration 029: rename player* → member* (tables, columns, RLS policies)
// Guarded renames use column/table existence checks so re-runs are safe.

// ── cells.player_id → cells.member_id ──────────────────────────────────────
try {
    $pid_cells = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema='{$schema}' AND table_name='cells' AND column_name='player_id'"
    )->fetchColumn();
    if ($pid_cells) {
        $pdo->exec("ALTER TABLE {$schema}.cells RENAME COLUMN player_id TO member_id");
        // Index rename is best-effort; name may differ across installs
        $pdo->exec("ALTER INDEX IF EXISTS {$schema}.idx_cells_player_id RENAME TO idx_cells_member_id");
        error_log('team-manager: migration 029 cells.player_id → member_id');
    }
} catch (PDOException $e) {
    error_log('team-manager: migration 029 cells rename skipped — ' . $e->getMessage());
}

// ── users.player_id → users.member_id ──────────────────────────────────────
try {
    $pid_users = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema='{$schema}' AND table_name='users' AND column_name='player_id'"
    )->fetchColumn();
    if ($pid_users) {
        $pdo->exec("ALTER TABLE {$schema}.users RENAME COLUMN player_id TO member_id");
        error_log('team-manager: migration 029 users.player_id → member_id');
    }
} catch (PDOException $e) {
    error_log('team-manager: migration 029 users rename skipped — ' . $e->getMessage());
}

// ── player_attribute_values: rename player_id column + rename table ─────────
try {
    $pav_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema='{$schema}' AND table_name='player_attribute_values'"
    )->fetchColumn();
    if ($pav_exists) {
        $pav_pid = (bool)$pdo->query(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema='{$schema}' AND table_name='player_attribute_values' AND column_name='player_id'"
        )->fetchColumn();
        if ($pav_pid) {
            $pdo->exec("ALTER TABLE {$schema}.player_attribute_values RENAME COLUMN player_id TO member_id");
            $pdo->exec("ALTER INDEX IF EXISTS {$schema}.idx_pav_player RENAME TO idx_mav_member");
        }
        $pdo->exec("ALTER TABLE {$schema}.player_attribute_values RENAME TO member_attribute_values");
        error_log('team-manager: migration 029 player_attribute_values → member_attribute_values');
    }
} catch (PDOException $e) {
    error_log('team-manager: migration 029 player_attribute_values rename skipped — ' . $e->getMessage());
}

// ── player_attributes → member_attributes ───────────────────────────────────
try {
    $pa_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema='{$schema}' AND table_name='player_attributes'"
    )->fetchColumn();
    if ($pa_exists) {
        $pdo->exec("ALTER TABLE {$schema}.player_attributes RENAME TO member_attributes");
        error_log('team-manager: migration 029 player_attributes → member_attributes');
    }
} catch (PDOException $e) {
    error_log('team-manager: migration 029 player_attributes rename skipped — ' . $e->getMessage());
}

// ── player_attribute_groups → member_attribute_groups ───────────────────────
try {
    $pag_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema='{$schema}' AND table_name='player_attribute_groups'"
    )->fetchColumn();
    if ($pag_exists) {
        $pdo->exec("ALTER TABLE {$schema}.player_attribute_groups RENAME TO member_attribute_groups");
        error_log('team-manager: migration 029 player_attribute_groups → member_attribute_groups');
    }
} catch (PDOException $e) {
    error_log('team-manager: migration 029 player_attribute_groups rename skipped — ' . $e->getMessage());
}

// ── players → members ────────────────────────────────────────────────────────
try {
    $pl_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema='{$schema}' AND table_name='players'"
    )->fetchColumn();
    if ($pl_exists) {
        $pdo->exec("ALTER TABLE {$schema}.players RENAME TO members");
        $pdo->exec("ALTER INDEX IF EXISTS {$schema}.idx_players_club RENAME TO idx_members_club");
        error_log('team-manager: migration 029 players → members');
    }
} catch (PDOException $e) {
    error_log('team-manager: migration 029 players rename skipped — ' . $e->getMessage());
}

// ── Recreate all RLS policies touching renamed tables/columns (always run) ─
// cells: reference member_id column
try {
    $pdo->exec("DROP POLICY IF EXISTS cells_insert ON {$schema}.cells");
    $pdo->exec("CREATE POLICY cells_insert ON {$schema}.cells FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (current_setting('app.current_role', true) = 'member'
            AND member_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (SELECT 1 FROM {$schema}.lists
                        WHERE lists.id = cells.list_id
                        AND lists.visibility = 'public'
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");
    $pdo->exec("DROP POLICY IF EXISTS cells_ownership_update ON {$schema}.cells");
    $pdo->exec("CREATE POLICY cells_ownership_update ON {$schema}.cells FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (current_setting('app.current_role', true) = 'member'
            AND member_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (SELECT 1 FROM {$schema}.lists
                        WHERE lists.id = cells.list_id
                        AND lists.visibility = 'public'
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");
} catch (PDOException $e) {
    error_log('team-manager: migration 029 cells RLS skipped — ' . $e->getMessage());
}

// members table (renamed from players): drop old player_* policy names, create members_*
foreach (['players_select','players_insert','players_update','players_delete',
          'members_select','members_insert','members_update','members_delete'] as $_pol) {
    try { $pdo->exec("DROP POLICY IF EXISTS {$_pol} ON {$schema}.members"); } catch (PDOException $e) {}
}
try { $pdo->exec("ALTER TABLE IF EXISTS {$schema}.members ENABLE ROW LEVEL SECURITY"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE IF EXISTS {$schema}.members FORCE ROW LEVEL SECURITY"); } catch (PDOException $e) {}
try {
    $pdo->exec("CREATE POLICY members_select ON {$schema}.members FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) IN ('coordinator', 'member')
            AND EXISTS (
                SELECT 1 FROM {$schema}.users u
                WHERE u.member_id = members.id
                  AND u.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                  AND u.role = 'member'
            )
        )
        OR EXISTS (
            SELECT 1 FROM {$schema}.users u
            WHERE u.member_id = members.id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
    )");
    $pdo->exec("CREATE POLICY members_insert ON {$schema}.members FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY members_update ON {$schema}.members FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY members_delete ON {$schema}.members FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
} catch (PDOException $e) {
    error_log('team-manager: migration 029 members RLS skipped — ' . $e->getMessage());
}

// member_attribute_groups (renamed from player_attribute_groups)
try { $pdo->exec("ALTER TABLE IF EXISTS {$schema}.member_attribute_groups ENABLE ROW LEVEL SECURITY"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE IF EXISTS {$schema}.member_attribute_groups FORCE ROW LEVEL SECURITY"); } catch (PDOException $e) {}
foreach (['pag_select','pag_insert','pag_update','pag_delete',
          'mag_select','mag_insert','mag_update','mag_delete'] as $_pol) {
    try { $pdo->exec("DROP POLICY IF EXISTS {$_pol} ON {$schema}.member_attribute_groups"); } catch (PDOException $e) {}
}
try {
    $pdo->exec("CREATE POLICY mag_select ON {$schema}.member_attribute_groups FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
    )");
    $pdo->exec("CREATE POLICY mag_insert ON {$schema}.member_attribute_groups FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY mag_update ON {$schema}.member_attribute_groups FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY mag_delete ON {$schema}.member_attribute_groups FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
} catch (PDOException $e) {
    error_log('team-manager: migration 029 member_attribute_groups RLS skipped — ' . $e->getMessage());
}

// member_attributes (renamed from player_attributes)
try { $pdo->exec("ALTER TABLE IF EXISTS {$schema}.member_attributes ENABLE ROW LEVEL SECURITY"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE IF EXISTS {$schema}.member_attributes FORCE ROW LEVEL SECURITY"); } catch (PDOException $e) {}
foreach (['pa_select','pa_insert','pa_update','pa_delete',
          'ma_select','ma_insert','ma_update','ma_delete'] as $_pol) {
    try { $pdo->exec("DROP POLICY IF EXISTS {$_pol} ON {$schema}.member_attributes"); } catch (PDOException $e) {}
}
try {
    $pdo->exec("CREATE POLICY ma_select ON {$schema}.member_attributes FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (current_setting('app.current_role', true) = 'member' AND visible_to_player = TRUE)
    )");
    $pdo->exec("CREATE POLICY ma_insert ON {$schema}.member_attributes FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY ma_update ON {$schema}.member_attributes FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY ma_delete ON {$schema}.member_attributes FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
} catch (PDOException $e) {
    error_log('team-manager: migration 029 member_attributes RLS skipped — ' . $e->getMessage());
}

// member_attribute_values (renamed from player_attribute_values)
// member_id column references members.id (renamed from players.id)
// member_attributes table referenced (renamed from player_attributes)
try { $pdo->exec("ALTER TABLE IF EXISTS {$schema}.member_attribute_values ENABLE ROW LEVEL SECURITY"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE IF EXISTS {$schema}.member_attribute_values FORCE ROW LEVEL SECURITY"); } catch (PDOException $e) {}
foreach (['pav_select','pav_insert','pav_update',
          'mav_select','mav_insert','mav_update'] as $_pol) {
    try { $pdo->exec("DROP POLICY IF EXISTS {$_pol} ON {$schema}.member_attribute_values"); } catch (PDOException $e) {}
}
try {
    $pdo->exec("CREATE POLICY mav_select ON {$schema}.member_attribute_values FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$schema}.users u
                WHERE u.member_id = member_attribute_values.member_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$schema}.member_attributes ma
                WHERE ma.id = member_attribute_values.attribute_id
                  AND ma.visible_to_player = TRUE
            )
        )
    )");
    $pdo->exec("CREATE POLICY mav_insert ON {$schema}.member_attribute_values FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$schema}.users u
                WHERE u.member_id = member_attribute_values.member_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$schema}.member_attributes ma
                WHERE ma.id = member_attribute_values.attribute_id
                  AND ma.editable_by_player = TRUE
            )
        )
    )");
    $pdo->exec("CREATE POLICY mav_update ON {$schema}.member_attribute_values FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$schema}.users u
                WHERE u.member_id = member_attribute_values.member_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$schema}.member_attributes ma
                WHERE ma.id = member_attribute_values.attribute_id
                  AND ma.editable_by_player = TRUE
            )
        )
    )");
} catch (PDOException $e) {
    error_log('team-manager: migration 029 member_attribute_values RLS skipped — ' . $e->getMessage());
}

error_log('team-manager: migration 029 complete');
```

ALSO update `db_init_schema()` (around line 1444+) in connection.php:
- `cells` table: change `player_id  INTEGER NOT NULL,` → `member_id  INTEGER NOT NULL,`
- Index: `idx_cells_player_id` → `idx_cells_member_id`
- Table `player_attribute_groups` → `member_attribute_groups`
- Table `player_attributes` → `member_attributes`; update `group_id` FK to reference `member_attribute_groups`
- Table `player_attribute_values` → `member_attribute_values`; `player_id INTEGER NOT NULL REFERENCES {$s}.players(id)` → `member_id INTEGER NOT NULL REFERENCES {$s}.members(id)`; update `attribute_id` FK to `member_attributes`; `UNIQUE (player_id, attribute_id)` → `UNIQUE (member_id, attribute_id)`; index `idx_pav_player` → `idx_mav_member`
- Table `players` → `members`; index `idx_players_club` → `idx_members_club`

ALSO update `db_init_rls()` (around line 1578+):
- `cells_insert` policy body: `player_id` → `member_id`
- `cells_ownership_update` policy body: `player_id` → `member_id`
- Replace all RLS blocks for `players`, `player_attribute_groups`, `player_attributes`, `player_attribute_values` with the new names + policy SQL from migration 029 above (mag_*, ma_*, mav_* policies on member_attribute_groups, member_attributes, member_attribute_values; members_* on members table)

ALSO update within `maybe_migrate_db()` — existing migration code that recreates cells policies with old `player_id` column name. Search for all occurrences of `AND player_id =` inside string arguments to `$pdo->exec()` in migrations 004, 005, 008 and change to `AND member_id =`. These migrations will never run again (their guard conditions check for obsolete role values), but update for code consistency.

ALSO update migration 012 RLS block: the conditional `if ($clubs_exists)` block contains RLS policies for `players`, `player_attribute_groups`, `player_attributes`, `player_attribute_values`. Update all table name references in those policy SQL strings to the new names (members, member_attribute_groups, member_attributes, member_attribute_values). Update `u.player_id` → `u.member_id` in all subqueries within those policies. Update policy names from `players_*`/`pag_*`/`pa_*`/`pav_*` to `members_*`/`mag_*`/`ma_*`/`mav_*`. The unconditional `ALTER TABLE users ADD COLUMN IF NOT EXISTS player_id` at line 732 — leave as-is (migration 029 renames it).

ALSO update migrations 015, 016, 019, 027: these recreate `pav_select`, `players_select` policies — update all `u.player_id` → `u.member_id`, table `players` → `members`, table `player_attribute_values` → `member_attribute_values`, table `player_attributes` → `member_attributes`, policy names as above.

ALSO update migrations 024: `player_id` references in the DO block (backfill loop) and `ALTER TABLE users ALTER COLUMN player_id` → `member_id`. Note: this migration may or may not have run; the DO block INSERT sets `player_id` on users — update to `member_id`.

Update `database/schema.sql`:
- Rename table `players` → `members` and index `idx_players_club` → `idx_members_club`
- `player_attribute_groups` → `member_attribute_groups`
- `player_attributes` → `member_attributes`; FK to `member_attribute_groups`
- `player_attribute_values` → `member_attribute_values`; `player_id` → `member_id`; FK to `members`; FK to `member_attributes`; unique constraint and index updated
- `cells.player_id` → `cells.member_id`; index `idx_cells_player_id` → `idx_cells_member_id`

Update `database/rls_policies.sql`:
- All `player_id` column references in cells policies → `member_id`
- All `players` table policy blocks → `members` with `members_*` policy names
- `player_attribute_groups` → `member_attribute_groups` with `mag_*` policy names
- `player_attributes` → `member_attributes` with `ma_*` policy names
- `player_attribute_values` → `member_attribute_values`; `player_id` → `member_id`; refs to `player_attributes` → `member_attributes`; `pav_*` → `mav_*`
  </action>
  <verify>
    <automated>grep -n "player_id\|FROM players\b\|player_attribute_groups\|player_attributes\|player_attribute_values" /Users/sebastianwiller/Documents/github/team-manager/database/schema.sql | grep -v "-- " | grep -v "comment" | wc -l; echo "should be 0"</automated>
  </verify>
  <done>
    - `database/schema.sql` contains no `player_id`, `FROM players`, or `player_attribute_*` references
    - `database/rls_policies.sql` uses `member_id` in cells policies and `members`/`member_attribute_*` for table names
    - Migration 029 exists at end of `maybe_migrate_db()` with guarded renames and unconditional policy recreation
    - `db_init_schema()` creates `members`, `member_attribute_groups`, `member_attributes`, `member_attribute_values` with `member_id` columns
    - `db_init_rls()` creates cells policies using `member_id`
  </done>
</task>

<task type="auto">
  <name>Task 2: PHP code rename — handlers, templates, routing, function names, UI text</name>
  <files>
    src/auth/session.php,
    src/utils/helpers.php,
    src/templates/member/layout.php,
    src/member/player_profile_handler.php (→ rename → src/member/member_profile_handler.php),
    src/coordinator/player_profile_handler.php (→ rename → src/coordinator/member_profile_handler.php),
    src/templates/member/player_profile.php (→ rename → src/templates/member/member_profile.php),
    src/templates/coordinator/player_profile.php (→ rename → src/templates/coordinator/member_profile.php),
    public/index.php,
    src/coordinator/members_handler.php,
    src/coordinator/member_create_handler.php,
    src/coordinator/member_change_player_handler.php,
    src/coordinator/member_edit_email_handler.php,
    src/coordinator/player_edit_handler.php,
    src/coordinator/player_attribute_edit_handler.php,
    src/coordinator/player_link_handler.php,
    src/coordinator/players_handler.php,
    src/coordinator/list_row_edit_handler.php,
    src/coordinator/list_detail_handler.php,
    src/coordinator/list_create_handler.php,
    src/coordinator/list_settings_handler.php,
    src/coordinator/stats_handler.php,
    src/coordinator/list_notify_handler.php,
    src/coordinator/file_notify_handler.php,
    src/coordinator/ticker_create_handler.php,
    src/coordinator/ticker_detail_handler.php,
    src/admin/players_handler.php,
    src/admin/player_create_handler.php,
    src/admin/player_edit_handler.php,
    src/admin/player_action_handler.php,
    src/member/list_detail_handler.php,
    src/member/list_row_edit_handler.php,
    src/member/stats_handler.php,
    src/member/profile_attributes_handler.php,
    src/templates/coordinator/members.php,
    src/templates/coordinator/member_form.php,
    src/templates/coordinator/member_change_player.php,
    src/templates/coordinator/players.php,
    src/templates/coordinator/player_edit.php,
    src/templates/coordinator/list_row_form.php,
    src/templates/member/profile.php,
    src/templates/member/player_profile.php (→ renamed),
    src/templates/admin/attributes.php
  </files>
  <action>
Execute in this order:

**Step A — session.php function swap:**
In `src/auth/session.php`, make `require_member()` the canonical implementation and
`require_player()` call it (reverse the current arrangement):
```php
function require_member(): void {
    check_session_timeout();
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'member') {
        redirect('/login?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    $pdo = get_db();
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], 'member', (int)$_SESSION['user_id']);
}

/** @deprecated Use require_member() */
function require_player(): void {
    require_member();
}
```

**Step B — helpers.php SQL fix:**
In `src/utils/helpers.php`, in `prefill_number_cells()` (around line 74):
- `INSERT INTO cells (list_id, column_id, player_id, value)` → `INSERT INTO cells (list_id, column_id, member_id, value)`
- `ON CONFLICT (list_id, column_id, player_id) DO NOTHING` → `ON CONFLICT (list_id, column_id, member_id) DO NOTHING`

**Step C — render_player_page rename in layout.php:**
In `src/templates/member/layout.php`:
- Rename `function render_player_page(` → `function render_member_page(`
- Update docblock `@param` if present
- The active tab key `'player_profile'` stays as a STRING VALUE (nav tab identifier) — rename to `'member_profile'`

**Step D — File renames (git mv):**
```bash
cd /Users/sebastianwiller/Documents/github/team-manager
git mv src/member/player_profile_handler.php src/member/member_profile_handler.php
git mv src/coordinator/player_profile_handler.php src/coordinator/member_profile_handler.php
git mv src/templates/member/player_profile.php src/templates/member/member_profile.php
git mv src/templates/coordinator/player_profile.php src/templates/coordinator/member_profile.php
```

**Step E — index.php routing update:**
In `public/index.php`:
1. `/member/player-profile` route (around line 429): change to `/member/member-profile`
2. Handler path `src/member/player_profile_handler.php` → `src/member/member_profile_handler.php`
3. Handler path `src/coordinator/player_profile_handler.php` → `src/coordinator/member_profile_handler.php`

**Step F — Bulk SQL column rename across all handler files:**
Run on all `.php` files under `src/` EXCEPT `src/db/connection.php`:

Pattern replacements in SQL string literals (inside PHP heredocs/quoted strings):
- `player_id` → `member_id` (all SQL occurrences: column names, WHERE clauses, INSERT column lists, ON CONFLICT clauses, ORDER BY, etc.)
- `FROM players` → `FROM members` (word boundary: exact table name)
- `FROM players p` → `FROM members p`
- `JOIN players` → `JOIN members`
- `JOIN players p` → `JOIN members p`
- `players.id` → `members.id`
- `FROM player_attribute_groups` → `FROM member_attribute_groups`
- `JOIN player_attribute_groups` → `JOIN member_attribute_groups`
- `player_attribute_groups` (standalone in SQL) → `member_attribute_groups`
- `FROM player_attributes` → `FROM member_attributes`
- `JOIN player_attributes` → `JOIN member_attributes`
- `player_attributes` (standalone in SQL) → `member_attributes`
- `FROM player_attribute_values` → `FROM member_attribute_values`
- `JOIN player_attribute_values` → `JOIN member_attribute_values`
- `player_attribute_values` (standalone in SQL) → `member_attribute_values`

Note: In SQL alias patterns like `JOIN members p ON p.id = u.member_id` — the alias `p` can stay (it refers to the profile record). Only rename the TABLE NAME and COLUMN NAME.

**Step G — PHP variable rename across all handler files:**
In PHP code (not SQL strings) in all `.php` files under `src/`:
- `$player_id` → `$member_id`
- `$player_ids` → `$member_ids`
- `$player_attr` → `$member_attr`
- `$player_attr_visible` → `$member_attr_visible`
- `$linked_player_ids` → `$linked_member_ids`
- `$current_player_id` → `$current_member_id` (in member_change_player_handler.php and template)
- `$player` → `$member_profile` OR leave as `$player` if renaming conflicts (some files use both `$player` as a profile record AND `$member` as a user account — in those cases rename `$player` to `$profile` to avoid ambiguity). Check file-by-file: if `$player` is a player-profile row, rename to `$profile`. If it's a user with role='member', it stays `$member`.
  - `src/coordinator/player_edit_handler.php`: `$player` = player profile row → rename to `$profile`
  - `src/coordinator/member_profile_handler.php` (renamed): `$player` = player profile row → `$profile`; `$player_id` = player profile ID → `$member_id`
  - `src/member/member_profile_handler.php` (renamed): same
  - `src/admin/player_*_handler.php`: `$player` = player profile row → `$profile`
  - `src/coordinator/players_handler.php`: `$players` = array of player profiles → `$profiles`
- `require_player()` call sites → `require_member()` (there are 2: `src/member/list_row_edit_handler.php` and `src/member/stats_handler.php`)
- `render_player_page(` → `render_member_page(` in all member template include calls

**Step H — Template variable name updates:**
In templates:
- `$player_id` → `$member_id` (PHP variable references in template output, hrefs, forms)
- `$player` → `$profile` (where it refers to a player profile row, consistent with handlers)
- `$player_ids` → `$member_ids`
- `$players` → `$profiles` (where it's a list of player profile rows)
- Active tab key string `'player_profile'` → `'member_profile'` in both handler calls and layout tab matching

**Step I — Form field rename (affects handlers + templates together):**
Form field `player_id_link` is used in member_form.php, member_change_player.php templates AND
their handler files (member_create_handler.php reads `$_POST['player_id_link']`,
member_change_player_handler.php reads `$_POST['player_id_link']`):
- Template `id="player_id_link"` → `id="member_id_link"`
- Template `name="player_id_link"` → `name="member_id_link"`
- Template JS `document.getElementById('player_id_link')` → `document.getElementById('member_id_link')`
- Template `$_POST['player_id_link']` → `$_POST['member_id_link']`
- Handler `$_POST['player_id_link']` → `$_POST['member_id_link']`

**Step J — URL parameter rename in templates and handlers:**
`$_REQUEST['player_id']` in `src/coordinator/list_row_edit_handler.php` (line 13) →
`$_REQUEST['member_id']` PLUS update the template that generates the URL with `?player_id=X`
(check `src/templates/coordinator/list_detail.php` or similar for the row-edit link).

**Step K — UI text (German labels):**
In all files under `src/templates/`:
- `Spieler verknüpfen` → `Profil verknüpfen`
- `Neuen Spieler anlegen` → `Neues Profil anlegen`
- `Vorhandenen Spieler wählen` → `Vorhandenes Profil wählen`
- `Vorhandenen Spieler verknüpfen` → `Vorhandenes Profil verknüpfen`
- `Spieler wählen` → `Profil wählen`
- `Spieler auswählen` → `Profil auswählen`
- `— Spieler wählen —` → `— Profil wählen —`
- `Keine weiteren Spieler verfügbar` → `Keine weiteren Profile verfügbar`
- `Spieler` as standalone label for the link/select action → `Profil` (e.g., in player_profile.php line 112: "Spieler" button label)
- `Alle verfügbaren Spieler sind bereits verknüpft` → `Alle verfügbaren Profile sind bereits verknüpft`
- `Der Admin kann weitere Spieler anlegen` → `Der Admin kann weitere Profile anlegen`
- `Keine verknüpften Spieler` → `Keine verknüpften Profile`
- `Verknüpfe ein Mitglied mit einem Spieler über das Formular oben` → `Verknüpfe ein Mitglied mit einem Profil über das Formular oben`
- `X Spieler in diesem Team` → `X Profile in diesem Team`
- In admin attributes template: `visible_to_player` label "Spieler" checkbox/switch labels → "Mitglied"
- `/member/player-profile` href in `src/templates/member/profile.php` → `/member/member-profile`
  </action>
  <verify>
    <automated>grep -rn "render_player_page\|require_player()\|/member/player-profile\b" /Users/sebastianwiller/Documents/github/team-manager/src --include="*.php" | grep -v "session.php" | grep -v "deprecated\|shim\|alias" | wc -l; echo "should be 0"</automated>
  </verify>
  <done>
    - `render_player_page` no longer exists except as a shim in session.php (or removed)
    - `require_player()` only appears in session.php as deprecated shim
    - `/member/player-profile` does not appear in any template or route handler
    - `/member/member-profile` route exists in index.php dispatching to `src/member/member_profile_handler.php`
    - Files `src/member/member_profile_handler.php` and `src/coordinator/member_profile_handler.php` exist
    - All `FROM players` SQL uses `FROM members`; all `player_id` SQL column refs use `member_id`
    - Form field `player_id_link` renamed to `member_id_link` in both templates and handlers
    - No "Spieler" UI labels remain in templates (verified by grep for "Spieler" in template strings)
  </done>
</task>

</tasks>

<verification>
After both tasks:

1. App boots: load `/login` — no PHP fatal errors, no DB errors in error log
2. Migration 029 ran: check error log for "migration 029 complete" or check DB for `members` table name
3. Member can log in and access `/member/member-profile` (old URL `/member/player-profile` returns 404 or redirects)
4. Coordinator can access `/coordinator/players/{id}` — player profile page loads
5. Admin can access `/admin/players` — player management works
6. Member can edit their cells in a public list (RLS enforces `member_id`)
7. No `player_id` column references in DB (verify via psql or check info_schema)
</verification>

<success_criteria>
- DB migration 029 runs cleanly with no uncaught exceptions
- `members` table exists (renamed from `players`); `member_attribute_*` tables exist
- `cells.member_id` column exists; `cells_insert` and `cells_ownership_update` policies use `member_id`
- `users.member_id` column exists as FK to `members.id`
- All PHP handler files reference `FROM members`, `member_id`, `member_attribute_*` in SQL
- `render_member_page()` is the canonical function (called by all member templates)
- `/member/member-profile` route works; old `/member/player-profile` is gone
- No German "Spieler" UI labels remain in templates (profile-related labels now say "Profil")
</success_criteria>

<output>
After completion, create `.planning/quick/260823-pbq-rename-player-to-member-backend-db-ui/260823-pbq-SUMMARY.md`
</output>
