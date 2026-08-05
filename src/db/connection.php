<?php
// src/db/connection.php — PDO connection factory

require_once dirname(__DIR__, 2) . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        DB_HOST, DB_PORT, DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,  // CRITICAL for PostgreSQL — never set to true
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', DB_SCHEMA);
    $pdo->exec("SET search_path TO {$schema}, public");

    maybe_init_db($pdo);
    maybe_migrate_db($pdo);

    return $pdo;
}

/**
 * Initialize database schema on first boot.
 * Detected via users.username column — absent means schema is missing or incomplete.
 * Uses inline DDL (no file parsing) to avoid exec() quirks on shared hosting.
 */
function maybe_init_db(PDO $pdo): void {
    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', DB_SCHEMA);

    $complete = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = '{$schema}' AND table_name = 'users' AND column_name = 'username'"
    )->fetchColumn();
    if ($complete !== false) return;

    try {
        $pdo->exec("CREATE SCHEMA IF NOT EXISTS {$schema}");
    } catch (PDOException $e) {
        error_log('team-manager: schema creation skipped (' . $e->getMessage() . ')');
    }

    db_init_schema($pdo, $schema);
    db_init_rls($pdo, $schema);
}

/**
 * Idempotent incremental migrations. Runs on every boot — skips statements whose effect is
 * already present. Catches permission errors gracefully (e.g. app user doesn't own the table
 * in Docker dev setups where a superuser ran the initial schema).
 *
 * Defines DB_HAS_COACH_ONLY (bool) so handlers can build queries conditionally.
 */
function maybe_migrate_db(PDO $pdo): void {
    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', DB_SCHEMA);

    // Migration 001: coach_only flag on columns
    $col_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = '{$schema}' AND table_name = 'columns' AND column_name = 'coach_only'"
    )->fetchColumn();

    if (!$col_exists) {
        try {
            $pdo->exec(
                "ALTER TABLE {$schema}.columns
                 ADD COLUMN IF NOT EXISTS coach_only BOOLEAN NOT NULL DEFAULT FALSE"
            );
            $col_exists = true;
        } catch (PDOException $e) {
            error_log('team-manager: migration 001 ALTER skipped — ' . $e->getMessage());
        }
    }

    define('DB_HAS_COACH_ONLY', $col_exists);

    if ($col_exists) {
        // Migration 001: update columns_visibility_select RLS to respect coach_only
        try {
            $pdo->exec("DROP POLICY IF EXISTS columns_visibility_select ON {$schema}.columns");
            $pdo->exec("CREATE POLICY columns_visibility_select ON {$schema}.columns FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (list_id IS NULL
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (list_id IS NOT NULL
                    AND coach_only = FALSE
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                    AND EXISTS (SELECT 1 FROM {$schema}.lists
                                WHERE lists.id = columns.list_id
                                AND lists.visibility IN ('public', 'protected')))
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 001 RLS skipped — ' . $e->getMessage());
        }
    }

    // Migration 003: app_color setting
    try {
        $pdo->exec("INSERT INTO {$schema}.settings (key, value)
            VALUES ('app_color', '#2563eb') ON CONFLICT DO NOTHING");
    } catch (PDOException $e) {
        error_log('team-manager: migration 003 skipped — ' . $e->getMessage());
    }

    // Migration 002: lists_delete RLS policy (missing from initial schema)
    try {
        $pdo->exec("DROP POLICY IF EXISTS lists_delete ON {$schema}.lists");
        $pdo->exec("CREATE POLICY lists_delete ON {$schema}.lists FOR DELETE USING (
            current_setting('app.is_admin', true) = 'true'
            OR (current_setting('app.current_role', true) = 'coordinator'
                AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
        )");
    } catch (PDOException $e) {
        error_log('team-manager: migration 002 RLS skipped — ' . $e->getMessage());
    }

    // Migration 004: rename role values coach→moderator, player→mitglied
    try {
        $old_roles_exist = (bool)$pdo->query(
            "SELECT 1 FROM {$schema}.users WHERE role IN ('coach', 'player') LIMIT 1"
        )->fetchColumn();
        if ($old_roles_exist) {
            $pdo->exec("ALTER TABLE {$schema}.users DROP CONSTRAINT IF EXISTS users_role_check");
            $pdo->exec("UPDATE {$schema}.users SET role = 'coordinator' WHERE role = 'coach'");
            $pdo->exec("UPDATE {$schema}.users SET role = 'member'  WHERE role = 'player'");
            $pdo->exec("ALTER TABLE {$schema}.users ADD CONSTRAINT users_role_check CHECK (role IN ('coordinator', 'member'))");
            // Recreate all role-dependent RLS policies with new values
            // Lists
            $pdo->exec("DROP POLICY IF EXISTS lists_visibility_select ON {$schema}.lists");
            $pdo->exec("CREATE POLICY lists_visibility_select ON {$schema}.lists FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (visibility IN ('public', 'protected') AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS lists_insert ON {$schema}.lists");
            $pdo->exec("CREATE POLICY lists_insert ON {$schema}.lists FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS lists_update ON {$schema}.lists");
            $pdo->exec("CREATE POLICY lists_update ON {$schema}.lists FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS lists_delete ON {$schema}.lists");
            $pdo->exec("CREATE POLICY lists_delete ON {$schema}.lists FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            // Columns visibility (respects coach_only)
            $pdo->exec("DROP POLICY IF EXISTS columns_visibility_select ON {$schema}.columns");
            $pdo->exec("CREATE POLICY columns_visibility_select ON {$schema}.columns FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (list_id IS NULL AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (list_id IS NOT NULL AND coach_only = FALSE AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = columns.list_id AND lists.visibility IN ('public', 'protected')))
            )");
            $pdo->exec("DROP POLICY IF EXISTS columns_insert ON {$schema}.columns");
            $pdo->exec("CREATE POLICY columns_insert ON {$schema}.columns FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            // list_global_columns
            $pdo->exec("DROP POLICY IF EXISTS lgc_insert ON {$schema}.list_global_columns");
            $pdo->exec("CREATE POLICY lgc_insert ON {$schema}.list_global_columns FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = list_global_columns.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            $pdo->exec("DROP POLICY IF EXISTS lgc_delete ON {$schema}.list_global_columns");
            $pdo->exec("CREATE POLICY lgc_delete ON {$schema}.list_global_columns FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = list_global_columns.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            // Cells
            $pdo->exec("DROP POLICY IF EXISTS cells_visibility_select ON {$schema}.cells");
            $pdo->exec("CREATE POLICY cells_visibility_select ON {$schema}.cells FOR SELECT USING (
                EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = cells.list_id AND (
                    current_setting('app.is_admin', true) = 'true'
                    OR (current_setting('app.current_role', true) = 'coordinator' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                    OR (lists.visibility IN ('public', 'protected') AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                ))
            )");
            $pdo->exec("DROP POLICY IF EXISTS cells_insert ON {$schema}.cells");
            $pdo->exec("CREATE POLICY cells_insert ON {$schema}.cells FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR current_setting('app.current_role', true) = 'coordinator'
                OR (current_setting('app.current_role', true) = 'member'
                    AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = cells.list_id AND lists.visibility = 'public' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            $pdo->exec("DROP POLICY IF EXISTS cells_ownership_update ON {$schema}.cells");
            $pdo->exec("CREATE POLICY cells_ownership_update ON {$schema}.cells FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
                OR current_setting('app.current_role', true) = 'coordinator'
                OR (current_setting('app.current_role', true) = 'member'
                    AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = cells.list_id AND lists.visibility = 'public' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            error_log('Migration 004: renamed roles coach→moderator, player→member and recreated RLS policies');
        }
    } catch (PDOException $e) {
        error_log('Migration 004 error: ' . $e->getMessage());
    }

    // Migration 005: rename role value mitglied→member
    try {
        $mitglied_exists = (bool)$pdo->query(
            "SELECT 1 FROM {$schema}.users WHERE role = 'mitglied' LIMIT 1"
        )->fetchColumn();
        if ($mitglied_exists) {
            $pdo->exec("ALTER TABLE {$schema}.users DROP CONSTRAINT IF EXISTS users_role_check");
            $pdo->exec("UPDATE {$schema}.users SET role = 'member' WHERE role = 'mitglied'");
            $pdo->exec("ALTER TABLE {$schema}.users ADD CONSTRAINT users_role_check CHECK (role IN ('coordinator', 'member'))");
        }
        // Ensure constraint is correct regardless (idempotent)
        $pdo->exec("ALTER TABLE {$schema}.users DROP CONSTRAINT IF EXISTS users_role_check");
        $pdo->exec("ALTER TABLE {$schema}.users ADD CONSTRAINT users_role_check CHECK (role IN ('coordinator', 'member'))");
        // Recreate cells RLS policies with 'member'
        $pdo->exec("DROP POLICY IF EXISTS cells_insert ON {$schema}.cells");
        $pdo->exec("CREATE POLICY cells_insert ON {$schema}.cells FOR INSERT WITH CHECK (
            current_setting('app.is_admin', true) = 'true'
            OR current_setting('app.current_role', true) = 'coordinator'
            OR (current_setting('app.current_role', true) = 'member'
                AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = cells.list_id AND lists.visibility = 'public' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
        )");
        $pdo->exec("DROP POLICY IF EXISTS cells_ownership_update ON {$schema}.cells");
        $pdo->exec("CREATE POLICY cells_ownership_update ON {$schema}.cells FOR UPDATE USING (
            current_setting('app.is_admin', true) = 'true'
            OR current_setting('app.current_role', true) = 'coordinator'
            OR (current_setting('app.current_role', true) = 'member'
                AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = cells.list_id AND lists.visibility = 'public' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
        )");
        error_log('Migration 005: role member ensured, RLS policies updated');
    } catch (PDOException $e) {
        error_log('Migration 005 error: ' . $e->getMessage());
    }

    // Migration 006: list_type column + free_list_rows table + drop cells.player_id FK
    // list_type allows 'member' (default) or 'free' lists where rows are custom labels.
    $list_type_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = '{$schema}' AND table_name = 'lists' AND column_name = 'list_type'"
    )->fetchColumn();

    if (!$list_type_exists) {
        try {
            $pdo->exec(
                "ALTER TABLE {$schema}.lists
                 ADD COLUMN IF NOT EXISTS list_type VARCHAR(10) NOT NULL DEFAULT 'member'
                 CHECK (list_type IN ('member', 'free'))"
            );
            $list_type_exists = true;
        } catch (PDOException $e) {
            error_log('team-manager: migration 006 ALTER lists.list_type skipped — ' . $e->getMessage());
        }
    }

    define('DB_HAS_LIST_TYPE', $list_type_exists);

    // Drop cells.player_id FK so free_list_rows IDs can be stored in cells.player_id.
    // The app layer enforces ownership; RLS enforces row visibility.
    try {
        // Find the actual FK constraint name (may differ from default)
        $fk_name = $pdo->query(
            "SELECT tc.constraint_name FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
             WHERE tc.table_schema = '{$schema}' AND tc.table_name = 'cells'
               AND tc.constraint_type = 'FOREIGN KEY'
               AND kcu.column_name = 'player_id'
             LIMIT 1"
        )->fetchColumn();
        if ($fk_name) {
            $pdo->exec("ALTER TABLE {$schema}.cells DROP CONSTRAINT IF EXISTS " . $fk_name);
        }
    } catch (PDOException $e) {
        error_log('team-manager: migration 006 drop cells.player_id FK skipped — ' . $e->getMessage());
    }

    // Create free_list_rows table
    $flr_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = '{$schema}' AND table_name = 'free_list_rows'"
    )->fetchColumn();

    if (!$flr_exists) {
        try {
            $pdo->exec(
                "CREATE TABLE {$schema}.free_list_rows (
                    id         SERIAL PRIMARY KEY,
                    list_id    INTEGER NOT NULL REFERENCES {$schema}.lists(id) ON DELETE CASCADE,
                    label      TEXT NOT NULL,
                    position   INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_flr_list_id ON {$schema}.free_list_rows(list_id)");
            $flr_exists = true;
        } catch (PDOException $e) {
            error_log('team-manager: migration 006 CREATE free_list_rows skipped — ' . $e->getMessage());
        }
    }

    // RLS on free_list_rows (drop-and-recreate for idempotency)
    if ($flr_exists) {
        try {
            $pdo->exec("ALTER TABLE {$schema}.free_list_rows ENABLE ROW LEVEL SECURITY");
        } catch (PDOException $e) {
            error_log('team-manager: migration 006 ENABLE RLS free_list_rows skipped — ' . $e->getMessage());
        }
        try {
            $pdo->exec("ALTER TABLE {$schema}.free_list_rows FORCE ROW LEVEL SECURITY");
        } catch (PDOException $e) {
            error_log('team-manager: migration 006 FORCE RLS free_list_rows skipped (non-fatal) — ' . $e->getMessage());
        }
        try {
            $pdo->exec("DROP POLICY IF EXISTS flr_select ON {$schema}.free_list_rows");
            $pdo->exec("CREATE POLICY flr_select ON {$schema}.free_list_rows FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists
                                WHERE lists.id = free_list_rows.list_id
                                AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
                OR EXISTS (SELECT 1 FROM {$schema}.lists
                           WHERE lists.id = free_list_rows.list_id
                           AND lists.visibility IN ('public', 'protected')
                           AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS flr_insert ON {$schema}.free_list_rows");
            $pdo->exec("CREATE POLICY flr_insert ON {$schema}.free_list_rows FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists
                                WHERE lists.id = free_list_rows.list_id
                                AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            $pdo->exec("DROP POLICY IF EXISTS flr_delete ON {$schema}.free_list_rows");
            $pdo->exec("CREATE POLICY flr_delete ON {$schema}.free_list_rows FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists
                                WHERE lists.id = free_list_rows.list_id
                                AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 006 RLS free_list_rows skipped — ' . $e->getMessage());
        }
    }

    // Migration 007: columns_delete RLS policy (missing from initial schema)
    // Wrap in try/catch — shared hosts may deny CREATE POLICY; safe to skip with warning.
    try {
        $policy_exists = (bool)$pdo->query(
            "SELECT 1 FROM pg_policies
             WHERE schemaname = '{$schema}' AND tablename = 'columns' AND policyname = 'columns_delete'"
        )->fetchColumn();
        if (!$policy_exists) {
            $pdo->exec("CREATE POLICY columns_delete ON {$schema}.columns FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            error_log('team-manager: migration 007 columns_delete policy created');
        }
    } catch (PDOException $e) {
        error_log('team-manager: migration 007 skipped — ' . $e->getMessage());
    }

    // Migration 008: rename role value moderator→coordinator
    try {
        $moderator_exists = (bool)$pdo->query(
            "SELECT 1 FROM {$schema}.users WHERE role = 'moderator' LIMIT 1"
        )->fetchColumn();
        if ($moderator_exists) {
            $pdo->exec("ALTER TABLE {$schema}.users ALTER COLUMN role TYPE VARCHAR(20)");
            $pdo->exec("ALTER TABLE {$schema}.users DROP CONSTRAINT IF EXISTS users_role_check");
            $pdo->exec("UPDATE {$schema}.users SET role = 'coordinator' WHERE role = 'moderator'");
            $pdo->exec("ALTER TABLE {$schema}.users ADD CONSTRAINT users_role_check CHECK (role IN ('coordinator', 'member'))");
            // Recreate all RLS policies that reference the 'moderator' role string with 'coordinator'
            // Lists
            $pdo->exec("DROP POLICY IF EXISTS lists_visibility_select ON {$schema}.lists");
            $pdo->exec("CREATE POLICY lists_visibility_select ON {$schema}.lists FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (visibility IN ('public', 'protected') AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS lists_insert ON {$schema}.lists");
            $pdo->exec("CREATE POLICY lists_insert ON {$schema}.lists FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS lists_update ON {$schema}.lists");
            $pdo->exec("CREATE POLICY lists_update ON {$schema}.lists FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS lists_delete ON {$schema}.lists");
            $pdo->exec("CREATE POLICY lists_delete ON {$schema}.lists FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            // Columns
            $pdo->exec("DROP POLICY IF EXISTS columns_visibility_select ON {$schema}.columns");
            $pdo->exec("CREATE POLICY columns_visibility_select ON {$schema}.columns FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (list_id IS NULL AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (list_id IS NOT NULL AND coach_only = FALSE AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = columns.list_id AND lists.visibility IN ('public', 'protected')))
            )");
            $pdo->exec("DROP POLICY IF EXISTS columns_insert ON {$schema}.columns");
            $pdo->exec("CREATE POLICY columns_insert ON {$schema}.columns FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS columns_delete ON {$schema}.columns");
            $pdo->exec("CREATE POLICY columns_delete ON {$schema}.columns FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator' AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            // list_global_columns
            $pdo->exec("DROP POLICY IF EXISTS lgc_insert ON {$schema}.list_global_columns");
            $pdo->exec("CREATE POLICY lgc_insert ON {$schema}.list_global_columns FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = list_global_columns.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            $pdo->exec("DROP POLICY IF EXISTS lgc_delete ON {$schema}.list_global_columns");
            $pdo->exec("CREATE POLICY lgc_delete ON {$schema}.list_global_columns FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = list_global_columns.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            // Cells
            $pdo->exec("DROP POLICY IF EXISTS cells_visibility_select ON {$schema}.cells");
            $pdo->exec("CREATE POLICY cells_visibility_select ON {$schema}.cells FOR SELECT USING (
                EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = cells.list_id AND (
                    current_setting('app.is_admin', true) = 'true'
                    OR (current_setting('app.current_role', true) = 'coordinator' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                    OR (lists.visibility IN ('public', 'protected') AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                ))
            )");
            $pdo->exec("DROP POLICY IF EXISTS cells_insert ON {$schema}.cells");
            $pdo->exec("CREATE POLICY cells_insert ON {$schema}.cells FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR current_setting('app.current_role', true) = 'coordinator'
                OR (current_setting('app.current_role', true) = 'member'
                    AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = cells.list_id AND lists.visibility = 'public' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            $pdo->exec("DROP POLICY IF EXISTS cells_ownership_update ON {$schema}.cells");
            $pdo->exec("CREATE POLICY cells_ownership_update ON {$schema}.cells FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
                OR current_setting('app.current_role', true) = 'coordinator'
                OR (current_setting('app.current_role', true) = 'member'
                    AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = cells.list_id AND lists.visibility = 'public' AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            // free_list_rows
            $pdo->exec("DROP POLICY IF EXISTS flr_select ON {$schema}.free_list_rows");
            $pdo->exec("CREATE POLICY flr_select ON {$schema}.free_list_rows FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = free_list_rows.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
                OR EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = free_list_rows.list_id AND lists.visibility IN ('public', 'protected') AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS flr_insert ON {$schema}.free_list_rows");
            $pdo->exec("CREATE POLICY flr_insert ON {$schema}.free_list_rows FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = free_list_rows.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            $pdo->exec("DROP POLICY IF EXISTS flr_delete ON {$schema}.free_list_rows");
            $pdo->exec("CREATE POLICY flr_delete ON {$schema}.free_list_rows FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (SELECT 1 FROM {$schema}.lists WHERE lists.id = free_list_rows.list_id AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
            )");
            error_log('team-manager: migration 008 renamed role moderator→coordinator and recreated all RLS policies');
        } else {
            // Ensure constraint is correct regardless (idempotent)
            $pdo->exec("ALTER TABLE {$schema}.users DROP CONSTRAINT IF EXISTS users_role_check");
            $pdo->exec("ALTER TABLE {$schema}.users ADD CONSTRAINT users_role_check CHECK (role IN ('coordinator', 'member'))");
        }
    } catch (PDOException $e) {
        error_log('team-manager: migration 008 error — ' . $e->getMessage());
    }

    // Migration 009: files table (markdown content type)
    $files_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = '{$schema}' AND table_name = 'files'"
    )->fetchColumn();

    if (!$files_exists) {
        try {
            $pdo->exec(
                "CREATE TABLE {$schema}.files (
                    id         SERIAL PRIMARY KEY,
                    team_id    INTEGER NOT NULL REFERENCES {$schema}.teams(id) ON DELETE CASCADE,
                    name       VARCHAR(255) NOT NULL,
                    content    TEXT NOT NULL DEFAULT '',
                    date       DATE NULL,
                    visibility VARCHAR(10) NOT NULL DEFAULT 'public'
                               CHECK (visibility IN ('public', 'protected', 'private')),
                    is_hidden  BOOLEAN NOT NULL DEFAULT FALSE,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_team_id ON {$schema}.files(team_id)");
            $files_exists = true;
        } catch (PDOException $e) {
            error_log('team-manager: migration 009 CREATE files skipped — ' . $e->getMessage());
        }
    }

    define('DB_HAS_FILES', $files_exists);

    if ($files_exists) {
        try {
            $pdo->exec("ALTER TABLE {$schema}.files ENABLE ROW LEVEL SECURITY");
        } catch (PDOException $e) {
            error_log('team-manager: migration 009 ENABLE RLS files skipped — ' . $e->getMessage());
        }
        try {
            $pdo->exec("ALTER TABLE {$schema}.files FORCE ROW LEVEL SECURITY");
        } catch (PDOException $e) {
            error_log('team-manager: migration 009 FORCE RLS files skipped (non-fatal) — ' . $e->getMessage());
        }
        try {
            $pdo->exec("DROP POLICY IF EXISTS files_select ON {$schema}.files");
            $pdo->exec("CREATE POLICY files_select ON {$schema}.files FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (visibility IN ('public', 'protected')
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS files_insert ON {$schema}.files");
            $pdo->exec("CREATE POLICY files_insert ON {$schema}.files FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS files_update ON {$schema}.files");
            $pdo->exec("CREATE POLICY files_update ON {$schema}.files FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                OR (current_setting('app.current_role', true) = 'member'
                    AND visibility = 'public'
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
            $pdo->exec("DROP POLICY IF EXISTS files_delete ON {$schema}.files");
            $pdo->exec("CREATE POLICY files_delete ON {$schema}.files FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
                OR (current_setting('app.current_role', true) = 'coordinator'
                    AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 009 RLS files skipped — ' . $e->getMessage());
        }
    }

    // Migration 010: team logo support
    // Add logo_path column to teams table (NULL = no logo)
    $logo_path_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = '{$schema}' AND table_name = 'teams' AND column_name = 'logo_path'"
    )->fetchColumn();
    if (!$logo_path_exists) {
        try {
            $pdo->exec(
                "ALTER TABLE {$schema}.teams
                 ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500) NULL"
            );
        } catch (PDOException $e) {
            error_log('team-manager: migration 010 ALTER teams.logo_path skipped — ' . $e->getMessage());
        }
    }
    // Add default_team_logo setting (value = relative path from ROOT_PATH)
    try {
        $pdo->exec(
            "INSERT INTO {$schema}.settings (key, value) VALUES ('default_team_logo', '')
             ON CONFLICT DO NOTHING"
        );
    } catch (PDOException $e) {
        error_log('team-manager: migration 010 settings default_team_logo skipped — ' . $e->getMessage());
    }

    // Migration 011: time_start and time_end on lists (optional per-event times for ICS)
    $time_start_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = '{$schema}' AND table_name = 'lists' AND column_name = 'time_start'"
    )->fetchColumn();

    if (!$time_start_exists) {
        try {
            $pdo->exec(
                "ALTER TABLE {$schema}.lists
                 ADD COLUMN IF NOT EXISTS time_start TIME NULL,
                 ADD COLUMN IF NOT EXISTS time_end   TIME NULL"
            );
            $time_start_exists = true;
        } catch (PDOException $e) {
            error_log('team-manager: migration 011 ALTER lists.time_start/time_end skipped — ' . $e->getMessage());
        }
    }

    define('DB_HAS_LIST_TIMES', $time_start_exists);

    // Migration 012: clubs, players, team_memberships, coordinator_teams, player EAV
    $clubs_exists = (bool)$pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = '{$schema}' AND table_name = 'clubs'"
    )->fetchColumn();

    if (!$clubs_exists) {
        try {
            // Create tables in FK dependency order
            // 1. clubs (no FK to new tables)
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$schema}.clubs (
                id         SERIAL PRIMARY KEY,
                name       VARCHAR(100) NOT NULL,
                is_active  BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )");

            // 2. player_attribute_groups (no FK to new tables)
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$schema}.player_attribute_groups (
                id         SERIAL PRIMARY KEY,
                name       VARCHAR(100) NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )");

            // 3. players (FK → clubs)
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$schema}.players (
                id           SERIAL PRIMARY KEY,
                club_id      INTEGER REFERENCES {$schema}.clubs(id) ON DELETE SET NULL,
                first_name   VARCHAR(100) NOT NULL,
                last_name    VARCHAR(100) NOT NULL,
                description  TEXT NULL,
                phone        VARCHAR(50) NULL,
                contact_name VARCHAR(100) NULL,
                created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_players_club ON {$schema}.players(club_id)");

            // 4. team_memberships (FK → players, teams)
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$schema}.team_memberships (
                id        SERIAL PRIMARY KEY,
                player_id INTEGER NOT NULL REFERENCES {$schema}.players(id) ON DELETE CASCADE,
                team_id   INTEGER NOT NULL REFERENCES {$schema}.teams(id) ON DELETE CASCADE,
                joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                left_at   TIMESTAMPTZ NULL
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tm_player ON {$schema}.team_memberships(player_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tm_team ON {$schema}.team_memberships(team_id)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_tm_active ON {$schema}.team_memberships(player_id) WHERE left_at IS NULL");

            // 5. coordinator_teams (FK → users, teams)
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$schema}.coordinator_teams (
                id        SERIAL PRIMARY KEY,
                user_id   INTEGER NOT NULL REFERENCES {$schema}.users(id) ON DELETE CASCADE,
                team_id   INTEGER NOT NULL REFERENCES {$schema}.teams(id) ON DELETE CASCADE,
                joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                left_at   TIMESTAMPTZ NULL
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ct_user ON {$schema}.coordinator_teams(user_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ct_team ON {$schema}.coordinator_teams(team_id)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_ct_active ON {$schema}.coordinator_teams(user_id, team_id) WHERE left_at IS NULL");

            // 6. player_attributes (FK → player_attribute_groups)
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$schema}.player_attributes (
                id                 SERIAL PRIMARY KEY,
                group_id           INTEGER NOT NULL REFERENCES {$schema}.player_attribute_groups(id) ON DELETE CASCADE,
                name               VARCHAR(100) NOT NULL,
                visible_to_player  BOOLEAN NOT NULL DEFAULT TRUE,
                editable_by_player BOOLEAN NOT NULL DEFAULT FALSE,
                sort_order         INTEGER NOT NULL DEFAULT 0,
                created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pa_group ON {$schema}.player_attributes(group_id)");

            // 7. player_attribute_values (FK → players, player_attributes)
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$schema}.player_attribute_values (
                id           SERIAL PRIMARY KEY,
                player_id    INTEGER NOT NULL REFERENCES {$schema}.players(id) ON DELETE CASCADE,
                attribute_id INTEGER NOT NULL REFERENCES {$schema}.player_attributes(id) ON DELETE CASCADE,
                value        TEXT NOT NULL DEFAULT '',
                updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                UNIQUE (player_id, attribute_id)
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pav_player ON {$schema}.player_attribute_values(player_id)");

            // Backfill coordinator_teams BEFORE enabling RLS — no team context at migration time
            $pdo->exec(
                "INSERT INTO {$schema}.coordinator_teams (user_id, team_id, joined_at)
                 SELECT id, team_id, created_at FROM {$schema}.users
                 WHERE role = 'coordinator' AND team_id IS NOT NULL
                 ON CONFLICT DO NOTHING"
            );

            $clubs_exists = true;
        } catch (PDOException $e) {
            error_log('team-manager: migration 012 CREATE tables skipped — ' . $e->getMessage());
        }
    }

    // Always run — idempotent (IF NOT EXISTS); applies to both fresh and existing installs
    try {
        $pdo->exec("ALTER TABLE {$schema}.users ADD COLUMN IF NOT EXISTS player_id INTEGER REFERENCES {$schema}.players(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        error_log('team-manager: migration 012 ALTER users.player_id skipped — ' . $e->getMessage());
    }
    try {
        $pdo->exec("ALTER TABLE {$schema}.users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL");
    } catch (PDOException $e) {
        error_log('team-manager: migration 012 ALTER users.phone skipped — ' . $e->getMessage());
    }

    // RLS for all new tables (drop-and-recreate for idempotency — runs for both fresh and existing installs)
    if ($clubs_exists) {
        foreach (['clubs', 'player_attribute_groups', 'players', 'team_memberships',
                  'coordinator_teams', 'player_attributes', 'player_attribute_values'] as $tbl) {
            try {
                $pdo->exec("ALTER TABLE {$schema}.{$tbl} ENABLE ROW LEVEL SECURITY");
            } catch (PDOException $e) {
                error_log("team-manager: migration 012 ENABLE RLS {$tbl} — " . $e->getMessage());
            }
            try {
                $pdo->exec("ALTER TABLE {$schema}.{$tbl} FORCE ROW LEVEL SECURITY");
            } catch (PDOException $e) { /* non-fatal */ }
        }

        // clubs policies
        try {
            $pdo->exec("DROP POLICY IF EXISTS clubs_select ON {$schema}.clubs");
            $pdo->exec("CREATE POLICY clubs_select ON {$schema}.clubs FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
            )");
            $pdo->exec("DROP POLICY IF EXISTS clubs_insert ON {$schema}.clubs");
            $pdo->exec("CREATE POLICY clubs_insert ON {$schema}.clubs FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS clubs_update ON {$schema}.clubs");
            $pdo->exec("CREATE POLICY clubs_update ON {$schema}.clubs FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS clubs_delete ON {$schema}.clubs");
            $pdo->exec("CREATE POLICY clubs_delete ON {$schema}.clubs FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 012 RLS clubs skipped — ' . $e->getMessage());
        }

        // player_attribute_groups policies
        try {
            $pdo->exec("DROP POLICY IF EXISTS pag_select ON {$schema}.player_attribute_groups");
            $pdo->exec("CREATE POLICY pag_select ON {$schema}.player_attribute_groups FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
            )");
            $pdo->exec("DROP POLICY IF EXISTS pag_insert ON {$schema}.player_attribute_groups");
            $pdo->exec("CREATE POLICY pag_insert ON {$schema}.player_attribute_groups FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS pag_update ON {$schema}.player_attribute_groups");
            $pdo->exec("CREATE POLICY pag_update ON {$schema}.player_attribute_groups FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS pag_delete ON {$schema}.player_attribute_groups");
            $pdo->exec("CREATE POLICY pag_delete ON {$schema}.player_attribute_groups FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 012 RLS player_attribute_groups skipped — ' . $e->getMessage());
        }

        // players policies (no direct team_id — scope via team_memberships subquery)
        try {
            $pdo->exec("DROP POLICY IF EXISTS players_select ON {$schema}.players");
            $pdo->exec("CREATE POLICY players_select ON {$schema}.players FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR EXISTS (
                    SELECT 1 FROM {$schema}.users u
                    WHERE u.player_id = players.id
                      AND u.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                      AND u.role = 'member'
                )
                OR EXISTS (
                    SELECT 1 FROM {$schema}.users u
                    WHERE u.player_id = players.id
                      AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                )
            )");
            $pdo->exec("DROP POLICY IF EXISTS players_insert ON {$schema}.players");
            $pdo->exec("CREATE POLICY players_insert ON {$schema}.players FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS players_update ON {$schema}.players");
            $pdo->exec("CREATE POLICY players_update ON {$schema}.players FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 012 RLS players skipped — ' . $e->getMessage());
        }

        // team_memberships policies
        try {
            $pdo->exec("DROP POLICY IF EXISTS tm_select ON {$schema}.team_memberships");
            $pdo->exec("CREATE POLICY tm_select ON {$schema}.team_memberships FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            )");
            $pdo->exec("DROP POLICY IF EXISTS tm_insert ON {$schema}.team_memberships");
            $pdo->exec("CREATE POLICY tm_insert ON {$schema}.team_memberships FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS tm_update ON {$schema}.team_memberships");
            $pdo->exec("CREATE POLICY tm_update ON {$schema}.team_memberships FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 012 RLS team_memberships skipped — ' . $e->getMessage());
        }

        // coordinator_teams policies
        try {
            $pdo->exec("DROP POLICY IF EXISTS ct_select ON {$schema}.coordinator_teams");
            $pdo->exec("CREATE POLICY ct_select ON {$schema}.coordinator_teams FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )");
            $pdo->exec("DROP POLICY IF EXISTS ct_insert ON {$schema}.coordinator_teams");
            $pdo->exec("CREATE POLICY ct_insert ON {$schema}.coordinator_teams FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS ct_update ON {$schema}.coordinator_teams");
            $pdo->exec("CREATE POLICY ct_update ON {$schema}.coordinator_teams FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 012 RLS coordinator_teams skipped — ' . $e->getMessage());
        }

        // player_attributes policies (member reads only if visible_to_player = TRUE)
        try {
            $pdo->exec("DROP POLICY IF EXISTS pa_select ON {$schema}.player_attributes");
            $pdo->exec("CREATE POLICY pa_select ON {$schema}.player_attributes FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR current_setting('app.current_role', true) = 'coordinator'
                OR (
                    current_setting('app.current_role', true) = 'member'
                    AND visible_to_player = TRUE
                )
            )");
            $pdo->exec("DROP POLICY IF EXISTS pa_insert ON {$schema}.player_attributes");
            $pdo->exec("CREATE POLICY pa_insert ON {$schema}.player_attributes FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS pa_update ON {$schema}.player_attributes");
            $pdo->exec("CREATE POLICY pa_update ON {$schema}.player_attributes FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
            $pdo->exec("DROP POLICY IF EXISTS pa_delete ON {$schema}.player_attributes");
            $pdo->exec("CREATE POLICY pa_delete ON {$schema}.player_attributes FOR DELETE USING (
                current_setting('app.is_admin', true) = 'true'
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 012 RLS player_attributes skipped — ' . $e->getMessage());
        }

        // player_attribute_values policies (most complex)
        try {
            $pdo->exec("DROP POLICY IF EXISTS pav_select ON {$schema}.player_attribute_values");
            $pdo->exec("CREATE POLICY pav_select ON {$schema}.player_attribute_values FOR SELECT USING (
                current_setting('app.is_admin', true) = 'true'
                OR (
                    current_setting('app.current_role', true) = 'coordinator'
                    AND EXISTS (
                        SELECT 1 FROM {$schema}.team_memberships tm
                        WHERE tm.player_id = player_attribute_values.player_id
                          AND tm.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                          AND tm.left_at IS NULL
                    )
                )
                OR (
                    current_setting('app.current_role', true) = 'member'
                    AND EXISTS (
                        SELECT 1 FROM {$schema}.users u
                        WHERE u.player_id = player_attribute_values.player_id
                          AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                    )
                    AND EXISTS (
                        SELECT 1 FROM {$schema}.player_attributes pa
                        WHERE pa.id = player_attribute_values.attribute_id
                          AND pa.visible_to_player = TRUE
                    )
                )
            )");
            $pdo->exec("DROP POLICY IF EXISTS pav_insert ON {$schema}.player_attribute_values");
            $pdo->exec("CREATE POLICY pav_insert ON {$schema}.player_attribute_values FOR INSERT WITH CHECK (
                current_setting('app.is_admin', true) = 'true'
                OR current_setting('app.current_role', true) = 'coordinator'
                OR (
                    current_setting('app.current_role', true) = 'member'
                    AND EXISTS (
                        SELECT 1 FROM {$schema}.users u
                        WHERE u.player_id = player_attribute_values.player_id
                          AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                    )
                    AND EXISTS (
                        SELECT 1 FROM {$schema}.player_attributes pa
                        WHERE pa.id = player_attribute_values.attribute_id
                          AND pa.editable_by_player = TRUE
                    )
                )
            )");
            $pdo->exec("DROP POLICY IF EXISTS pav_update ON {$schema}.player_attribute_values");
            $pdo->exec("CREATE POLICY pav_update ON {$schema}.player_attribute_values FOR UPDATE USING (
                current_setting('app.is_admin', true) = 'true'
                OR current_setting('app.current_role', true) = 'coordinator'
                OR (
                    current_setting('app.current_role', true) = 'member'
                    AND EXISTS (
                        SELECT 1 FROM {$schema}.users u
                        WHERE u.player_id = player_attribute_values.player_id
                          AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
                    )
                    AND EXISTS (
                        SELECT 1 FROM {$schema}.player_attributes pa
                        WHERE pa.id = player_attribute_values.attribute_id
                          AND pa.editable_by_player = TRUE
                    )
                )
            )");
        } catch (PDOException $e) {
            error_log('team-manager: migration 012 RLS player_attribute_values skipped — ' . $e->getMessage());
        }
    }
}

/**
 * Create all application tables. Each exec() is its own autocommit transaction.
 * No IF NOT EXISTS — the maybe_init_db completeness check gates this function,
 * so we only run here on a fresh (or partial) schema.
 */
function db_init_schema(PDO $pdo, string $s): void {
    $pdo->exec("SET search_path TO {$s}, public");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.teams (
        id         SERIAL PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        is_active  BOOLEAN NOT NULL DEFAULT TRUE,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.users (
        id            SERIAL PRIMARY KEY,
        team_id       INTEGER REFERENCES {$s}.teams(id) ON DELETE SET NULL,
        role          VARCHAR(20) NOT NULL CHECK (role IN ('coordinator', 'member')),
        first_name    VARCHAR(100) NOT NULL,
        last_name     VARCHAR(100) NOT NULL,
        username      VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        is_active     BOOLEAN NOT NULL DEFAULT TRUE,
        created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON {$s}.users(username)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_team_id  ON {$s}.users(team_id)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.settings (
        key   VARCHAR(100) PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ''
    )");

    $pdo->exec("INSERT INTO {$s}.settings (key, value)
        VALUES ('app_title', 'Team Manager') ON CONFLICT DO NOTHING");

    $pdo->exec("INSERT INTO {$s}.settings (key, value)
        VALUES ('app_color', '#2563eb') ON CONFLICT DO NOTHING");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.lists (
        id            SERIAL PRIMARY KEY,
        team_id       INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
        name          VARCHAR(100) NOT NULL,
        visibility    VARCHAR(10) NOT NULL DEFAULT 'public'
                      CHECK (visibility IN ('public', 'protected', 'private')),
        list_type     VARCHAR(10) NOT NULL DEFAULT 'member'
                      CHECK (list_type IN ('member', 'free')),
        show_all_rows BOOLEAN NOT NULL DEFAULT FALSE,
        is_hidden     BOOLEAN NOT NULL DEFAULT FALSE,
        description   TEXT NULL,
        date          DATE NULL,
        created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lists_team_id    ON {$s}.lists(team_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lists_visibility ON {$s}.lists(visibility)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.columns (
        id         SERIAL PRIMARY KEY,
        team_id    INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
        list_id    INTEGER REFERENCES {$s}.lists(id) ON DELETE CASCADE,
        name       VARCHAR(100) NOT NULL,
        data_type  VARCHAR(10) NOT NULL CHECK (data_type IN ('boolean', 'number', 'text')),
        is_active  BOOLEAN NOT NULL DEFAULT TRUE,
        sort_order INTEGER NOT NULL DEFAULT 0,
        coach_only BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_columns_team_id ON {$s}.columns(team_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_columns_list_id ON {$s}.columns(list_id)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.list_global_columns (
        list_id   INTEGER NOT NULL REFERENCES {$s}.lists(id)   ON DELETE CASCADE,
        column_id INTEGER NOT NULL REFERENCES {$s}.columns(id) ON DELETE CASCADE,
        PRIMARY KEY (list_id, column_id)
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lgc_list_id   ON {$s}.list_global_columns(list_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lgc_column_id ON {$s}.list_global_columns(column_id)");

    // Note: player_id has no FK to users — it also stores free_list_rows.id for free lists.
    // The app layer enforces ownership; RLS enforces row visibility.
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.cells (
        id         SERIAL PRIMARY KEY,
        list_id    INTEGER NOT NULL REFERENCES {$s}.lists(id)    ON DELETE CASCADE,
        column_id  INTEGER NOT NULL REFERENCES {$s}.columns(id)  ON DELETE CASCADE,
        player_id  INTEGER NOT NULL,
        value      TEXT,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        UNIQUE (list_id, column_id, player_id)
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_list_id   ON {$s}.cells(list_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_column_id ON {$s}.cells(column_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_player_id ON {$s}.cells(player_id)");

    // free_list_rows: custom row labels for 'free' type lists
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.free_list_rows (
        id         SERIAL PRIMARY KEY,
        list_id    INTEGER NOT NULL REFERENCES {$s}.lists(id) ON DELETE CASCADE,
        label      TEXT NOT NULL,
        position   INTEGER NOT NULL DEFAULT 0,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_flr_list_id ON {$s}.free_list_rows(list_id)");

    // ── Phase 7: Live-Ticker tables ─────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.ticker_tags (
        id         SERIAL PRIMARY KEY,
        team_id    INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
        label      VARCHAR(50) NOT NULL,
        color      VARCHAR(20) NOT NULL DEFAULT 'secondary',
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticker_tags_team ON {$s}.ticker_tags(team_id)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.tickers (
        id          SERIAL PRIMARY KEY,
        team_id     INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
        name        VARCHAR(255) NOT NULL,
        description TEXT,
        status      VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'closed')),
        event_date  DATE NULL,
        start_time  TIME NULL,
        created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickers_team_status ON {$s}.tickers(team_id, status)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.ticker_messages (
        id         SERIAL PRIMARY KEY,
        ticker_id  INTEGER NOT NULL REFERENCES {$s}.tickers(id) ON DELETE CASCADE,
        tag_id     INTEGER REFERENCES {$s}.ticker_tags(id) ON DELETE SET NULL,
        message    VARCHAR(280) NOT NULL,
        timestamp  VARCHAR(5) NOT NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticker_messages_ticker_created ON {$s}.ticker_messages(ticker_id, created_at DESC)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.ticker_members (
        ticker_id INTEGER NOT NULL REFERENCES {$s}.tickers(id) ON DELETE CASCADE,
        user_id   INTEGER NOT NULL REFERENCES {$s}.users(id)   ON DELETE CASCADE,
        team_id   INTEGER NOT NULL REFERENCES {$s}.teams(id)   ON DELETE CASCADE,
        PRIMARY KEY (ticker_id, user_id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticker_members_user ON {$s}.ticker_members(user_id, team_id)");

    // ── Phase 8: Player & Club Management ─────────────────────────────────
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
}

/**
 * Apply RLS policies. Each statement is a separate exec().
 */
function db_init_rls(PDO $pdo, string $s): void {
    $pdo->exec("SET search_path TO {$s}, public");

    $pdo->exec("ALTER TABLE {$s}.users ENABLE ROW LEVEL SECURITY");
    $pdo->exec("ALTER TABLE {$s}.users FORCE ROW LEVEL SECURITY");

    $pdo->exec("CREATE POLICY team_isolation_users_select ON {$s}.users FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");

    $pdo->exec("CREATE POLICY team_isolation_users_insert ON {$s}.users FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");

    $pdo->exec("CREATE POLICY team_isolation_users_update ON {$s}.users FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");

    $pdo->exec("ALTER TABLE {$s}.lists ENABLE ROW LEVEL SECURITY");
    $pdo->exec("ALTER TABLE {$s}.lists FORCE ROW LEVEL SECURITY");

    $pdo->exec("CREATE POLICY lists_visibility_select ON {$s}.lists FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
        OR (visibility IN ('public', 'protected')
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("CREATE POLICY lists_insert ON {$s}.lists FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("CREATE POLICY lists_update ON {$s}.lists FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("CREATE POLICY lists_delete ON {$s}.lists FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("ALTER TABLE {$s}.columns ENABLE ROW LEVEL SECURITY");
    $pdo->exec("ALTER TABLE {$s}.columns FORCE ROW LEVEL SECURITY");

    $pdo->exec("CREATE POLICY columns_visibility_select ON {$s}.columns FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
        OR (list_id IS NULL
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
        OR (list_id IS NOT NULL
            AND coach_only = FALSE
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = columns.list_id
                        AND lists.visibility IN ('public', 'protected')))
    )");

    $pdo->exec("CREATE POLICY columns_insert ON {$s}.columns FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("CREATE POLICY columns_delete ON {$s}.columns FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("ALTER TABLE {$s}.list_global_columns ENABLE ROW LEVEL SECURITY");
    $pdo->exec("ALTER TABLE {$s}.list_global_columns FORCE ROW LEVEL SECURITY");

    $pdo->exec("CREATE POLICY lgc_select ON {$s}.list_global_columns FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR EXISTS (SELECT 1 FROM {$s}.lists
                   WHERE lists.id = list_global_columns.list_id
                   AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("CREATE POLICY lgc_insert ON {$s}.list_global_columns FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = list_global_columns.list_id
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    $pdo->exec("CREATE POLICY lgc_delete ON {$s}.list_global_columns FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = list_global_columns.list_id
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    $pdo->exec("ALTER TABLE {$s}.cells ENABLE ROW LEVEL SECURITY");
    $pdo->exec("ALTER TABLE {$s}.cells FORCE ROW LEVEL SECURITY");

    $pdo->exec("CREATE POLICY cells_visibility_select ON {$s}.cells FOR SELECT USING (
        EXISTS (SELECT 1 FROM {$s}.lists
                WHERE lists.id = cells.list_id
                AND (current_setting('app.is_admin', true) = 'true'
                     OR (current_setting('app.current_role', true) = 'coordinator'
                         AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                     OR (lists.visibility IN ('public', 'protected')
                         AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)))
    )");

    $pdo->exec("CREATE POLICY cells_insert ON {$s}.cells FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (current_setting('app.current_role', true) = 'member'
            AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = cells.list_id
                        AND lists.visibility = 'public'
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    $pdo->exec("CREATE POLICY cells_ownership_update ON {$s}.cells FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (current_setting('app.current_role', true) = 'member'
            AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = cells.list_id
                        AND lists.visibility = 'public'
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    // free_list_rows RLS
    $pdo->exec("ALTER TABLE {$s}.free_list_rows ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.free_list_rows FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS free_list_rows skipped (non-fatal) — ' . $e->getMessage());
    }

    $pdo->exec("CREATE POLICY flr_select ON {$s}.free_list_rows FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = free_list_rows.list_id
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
        OR EXISTS (SELECT 1 FROM {$s}.lists
                   WHERE lists.id = free_list_rows.list_id
                   AND lists.visibility IN ('public', 'protected')
                   AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("CREATE POLICY flr_insert ON {$s}.free_list_rows FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = free_list_rows.list_id
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    $pdo->exec("CREATE POLICY flr_delete ON {$s}.free_list_rows FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = free_list_rows.list_id
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    // ── Phase 7: Live-Ticker RLS ─────────────────────────────────────────
    $pdo->exec("ALTER TABLE {$s}.ticker_tags ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.ticker_tags FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS ticker_tags skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY ticker_tags_select ON {$s}.ticker_tags FOR SELECT USING (
        team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY ticker_tags_insert ON {$s}.ticker_tags FOR INSERT WITH CHECK (
        current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY ticker_tags_update ON {$s}.ticker_tags FOR UPDATE USING (
        current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY ticker_tags_delete ON {$s}.ticker_tags FOR DELETE USING (
        current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");

    $pdo->exec("ALTER TABLE {$s}.tickers ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.tickers FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS tickers skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY tickers_select ON {$s}.tickers FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY tickers_insert ON {$s}.tickers FOR INSERT WITH CHECK (
        current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY tickers_update ON {$s}.tickers FOR UPDATE USING (
        current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY tickers_delete ON {$s}.tickers FOR DELETE USING (
        current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");

    $pdo->exec("ALTER TABLE {$s}.ticker_messages ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.ticker_messages FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS ticker_messages skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY ticker_messages_select ON {$s}.ticker_messages FOR SELECT USING (
        EXISTS (
            SELECT 1 FROM {$s}.tickers
            WHERE tickers.id = ticker_messages.ticker_id
              AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    )");
    $pdo->exec("CREATE POLICY ticker_messages_insert ON {$s}.ticker_messages FOR INSERT WITH CHECK (
        current_setting('app.current_role', true) IN ('coordinator', 'member')
        AND EXISTS (
            SELECT 1 FROM {$s}.tickers
            WHERE tickers.id = ticker_messages.ticker_id
              AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    )");
    $pdo->exec("CREATE POLICY ticker_messages_update ON {$s}.ticker_messages FOR UPDATE USING (
        current_setting('app.current_role', true) IN ('coordinator', 'member')
        AND EXISTS (
            SELECT 1 FROM {$s}.tickers
            WHERE tickers.id = ticker_messages.ticker_id
              AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    )");
    $pdo->exec("CREATE POLICY ticker_messages_delete ON {$s}.ticker_messages FOR DELETE USING (
        current_setting('app.current_role', true) IN ('coordinator', 'member')
        AND EXISTS (
            SELECT 1 FROM {$s}.tickers
            WHERE tickers.id = ticker_messages.ticker_id
              AND tickers.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    )");

    $pdo->exec("ALTER TABLE {$s}.ticker_members ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.ticker_members FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS ticker_members skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY ticker_members_select ON {$s}.ticker_members FOR SELECT USING (
        team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        AND current_setting('app.current_role', true) IN ('coordinator', 'member')
    )");
    $pdo->exec("CREATE POLICY ticker_members_insert ON {$s}.ticker_members FOR INSERT WITH CHECK (
        current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY ticker_members_delete ON {$s}.ticker_members FOR DELETE USING (
        current_setting('app.current_role', true) = 'coordinator'
        AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");

    // ── Phase 8: Player & Club Management RLS ─────────────────────────────
    $pdo->exec("ALTER TABLE {$s}.clubs ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.clubs FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS clubs skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY clubs_select ON {$s}.clubs FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
    )");
    $pdo->exec("CREATE POLICY clubs_insert ON {$s}.clubs FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY clubs_update ON {$s}.clubs FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY clubs_delete ON {$s}.clubs FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    $pdo->exec("ALTER TABLE {$s}.player_attribute_groups ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.player_attribute_groups FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS player_attribute_groups skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY pag_select ON {$s}.player_attribute_groups FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
    )");
    $pdo->exec("CREATE POLICY pag_insert ON {$s}.player_attribute_groups FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY pag_update ON {$s}.player_attribute_groups FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY pag_delete ON {$s}.player_attribute_groups FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    $pdo->exec("ALTER TABLE {$s}.players ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.players FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS players skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY players_select ON {$s}.players FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR EXISTS (
            SELECT 1 FROM {$s}.team_memberships tm
            WHERE tm.player_id = players.id
              AND tm.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
              AND tm.left_at IS NULL
        )
        OR EXISTS (
            SELECT 1 FROM {$s}.users u
            WHERE u.player_id = players.id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
    )");
    $pdo->exec("CREATE POLICY players_insert ON {$s}.players FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY players_update ON {$s}.players FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    $pdo->exec("ALTER TABLE {$s}.team_memberships ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.team_memberships FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS team_memberships skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY tm_select ON {$s}.team_memberships FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY tm_insert ON {$s}.team_memberships FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY tm_update ON {$s}.team_memberships FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    $pdo->exec("ALTER TABLE {$s}.coordinator_teams ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.coordinator_teams FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS coordinator_teams skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY ct_select ON {$s}.coordinator_teams FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
    )");
    $pdo->exec("CREATE POLICY ct_insert ON {$s}.coordinator_teams FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY ct_update ON {$s}.coordinator_teams FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    $pdo->exec("ALTER TABLE {$s}.player_attributes ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.player_attributes FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS player_attributes skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY pa_select ON {$s}.player_attributes FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND visible_to_player = TRUE
        )
    )");
    $pdo->exec("CREATE POLICY pa_insert ON {$s}.player_attributes FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY pa_update ON {$s}.player_attributes FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY pa_delete ON {$s}.player_attributes FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    $pdo->exec("ALTER TABLE {$s}.player_attribute_values ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.player_attribute_values FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS player_attribute_values skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY pav_select ON {$s}.player_attribute_values FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (
                SELECT 1 FROM {$s}.team_memberships tm
                WHERE tm.player_id = player_attribute_values.player_id
                  AND tm.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                  AND tm.left_at IS NULL
            )
        )
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$s}.users u
                WHERE u.player_id = player_attribute_values.player_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$s}.player_attributes pa
                WHERE pa.id = player_attribute_values.attribute_id
                  AND pa.visible_to_player = TRUE
            )
        )
    )");
    $pdo->exec("CREATE POLICY pav_insert ON {$s}.player_attribute_values FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$s}.users u
                WHERE u.player_id = player_attribute_values.player_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$s}.player_attributes pa
                WHERE pa.id = player_attribute_values.attribute_id
                  AND pa.editable_by_player = TRUE
            )
        )
    )");
    $pdo->exec("CREATE POLICY pav_update ON {$s}.player_attribute_values FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$s}.users u
                WHERE u.player_id = player_attribute_values.player_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$s}.player_attributes pa
                WHERE pa.id = player_attribute_values.attribute_id
                  AND pa.editable_by_player = TRUE
            )
        )
    )");
}

/**
 * Set PostgreSQL session context for RLS team isolation.
 */
function set_team_context(PDO $pdo, int $team_id, ?string $role = null, ?int $user_id = null): void {
    $pdo->exec(
        "SELECT set_config('app.current_team_id', " . $pdo->quote((string)$team_id) . ", false)" .
        ", set_config('app.current_role', " . $pdo->quote((string)($role ?? '')) . ", false)" .
        ", set_config('app.current_user_id', " . $pdo->quote((string)($user_id ?? '')) . ", false)"
    );
}

/**
 * Grant admin bypass for RLS policies on this connection.
 */
function set_admin_context(PDO $pdo): void {
    $pdo->exec("SELECT set_config('app.is_admin', 'true', false)");
}

/**
 * Reset all RLS context GUCs to empty state.
 */
function reset_rls_context(PDO $pdo): void {
    $pdo->exec(
        "SELECT set_config('app.is_admin', '', false)" .
        ", set_config('app.current_team_id', '', false)" .
        ", set_config('app.current_role', '', false)" .
        ", set_config('app.current_user_id', '', false)"
    );
}
