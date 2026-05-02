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
                OR (current_setting('app.current_role', true) = 'coach'
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
        role          VARCHAR(10) NOT NULL CHECK (role IN ('coach', 'player')),
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.lists (
        id            SERIAL PRIMARY KEY,
        team_id       INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
        name          VARCHAR(100) NOT NULL,
        visibility    VARCHAR(10) NOT NULL DEFAULT 'public'
                      CHECK (visibility IN ('public', 'protected', 'private')),
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.cells (
        id         SERIAL PRIMARY KEY,
        list_id    INTEGER NOT NULL REFERENCES {$s}.lists(id)    ON DELETE CASCADE,
        column_id  INTEGER NOT NULL REFERENCES {$s}.columns(id)  ON DELETE CASCADE,
        player_id  INTEGER NOT NULL REFERENCES {$s}.users(id)    ON DELETE CASCADE,
        value      TEXT,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        UNIQUE (list_id, column_id, player_id)
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_list_id   ON {$s}.cells(list_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_column_id ON {$s}.cells(column_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_player_id ON {$s}.cells(player_id)");
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
        OR (current_setting('app.current_role', true) = 'coach'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
        OR (visibility IN ('public', 'protected')
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("CREATE POLICY lists_insert ON {$s}.lists FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coach'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("CREATE POLICY lists_update ON {$s}.lists FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coach'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    $pdo->exec("ALTER TABLE {$s}.columns ENABLE ROW LEVEL SECURITY");
    $pdo->exec("ALTER TABLE {$s}.columns FORCE ROW LEVEL SECURITY");

    $pdo->exec("CREATE POLICY columns_visibility_select ON {$s}.columns FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coach'
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
        OR (current_setting('app.current_role', true) = 'coach'
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
        OR (current_setting('app.current_role', true) = 'coach'
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = list_global_columns.list_id
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    $pdo->exec("CREATE POLICY lgc_delete ON {$s}.list_global_columns FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coach'
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
                     OR (current_setting('app.current_role', true) = 'coach'
                         AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
                     OR (lists.visibility IN ('public', 'protected')
                         AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)))
    )");

    $pdo->exec("CREATE POLICY cells_insert ON {$s}.cells FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coach'
        OR (current_setting('app.current_role', true) = 'player'
            AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = cells.list_id
                        AND lists.visibility = 'public'
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    $pdo->exec("CREATE POLICY cells_ownership_update ON {$s}.cells FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coach'
        OR (current_setting('app.current_role', true) = 'player'
            AND player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = cells.list_id
                        AND lists.visibility = 'public'
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
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
