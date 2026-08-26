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
 * All incremental migrations (001–032) were applied to production on 2026-08-25.
 * Fresh installs receive the complete schema via db_init_schema() + db_init_rls().
 *
 * Migration history summary:
 *  001  coach_only flag on columns; RLS updated to respect it
 *  002  lists_delete RLS policy (was missing from initial schema)
 *  003  app_color setting
 *  004  Roles renamed: coach → coordinator, player → member; all RLS policies recreated
 *  005  Role value 'mitglied' consolidated to 'member'
 *  006  list_type column (member | free) + free_list_rows table; cells.player_id FK dropped
 *  007  columns_delete RLS policy
 *  008  Role value 'moderator' renamed to 'coordinator'
 *  009  files table (Markdown content type) + RLS policies
 *  010  teams.logo_path + default_team_logo setting
 *  011  lists.time_start / time_end for ICS calendar export
 *  012  clubs, members, coordinator_teams, member_attribute groups/attributes/values + RLS
 *  013  users_delete RLS policy
 *  014  users.club_id (coordinator ↔ club relation)
 *  015  member_attribute_values RLS policies fixed (table rename from player_attribute_values)
 *  016  members_select RLS fixed; team_memberships table dropped
 *  017  members.confirmed_at + users.confirmed_at (GDPR first-login confirmation)
 *  018  members.contact_phone
 *  019  mav_select coordinator arm widened
 *  020  teams.sort_order
 *  021  members.contact_email
 *  022  members.email backfilled from users.email
 *  023  users first/last name synced from members (members is canonical)
 *  024  users.member_id backfilled + NOT NULL enforced
 *  025a members.is_active (soft-delete); 025b deprecated personal columns dropped from users
 *  026  members_delete RLS policy
 *  027  members_select widened (all team roles can see team member records)
 *  028  columns_update + lgc_update RLS policies; is_system flag on columns
 *  029  member_attributes.data_type (text | date)
 *  030  events table + RLS (calendar events per team, ICS export with VALARM)
 *  031  events.location
 *  032  events.is_hidden (default true, hidden in list view by default)
 */
function maybe_migrate_db(PDO $pdo): void {
    // All migrations applied — no-op for existing installs.
    // These constants are always true: production and fresh installs both have all tables.
    define('DB_HAS_FILES',      true);
    define('DB_HAS_LIST_TIMES', true);
    define('DB_HAS_EVENTS',     true);
    define('DB_HAS_COACH_ONLY', true);

    // (Migration body removed 2026-08-25 — all 032 migrations applied to production.)
}


/**
 * Create all application tables. Each exec() is its own autocommit transaction.
 * IF NOT EXISTS throughout — safe to re-run on an existing schema.
 */
function db_init_schema(PDO $pdo, string $s): void {
    $pdo->exec("SET search_path TO {$s}, public");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.teams (
        id         SERIAL PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        is_active  BOOLEAN NOT NULL DEFAULT TRUE,
        sort_order INTEGER NOT NULL DEFAULT 0,
        logo_path  VARCHAR(500) NULL,
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
        confirmed_at  TIMESTAMPTZ NULL,
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

    $pdo->exec("INSERT INTO {$s}.settings (key, value)
        VALUES ('default_team_logo', '') ON CONFLICT DO NOTHING");

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
        location      VARCHAR(255) NULL,
        time_start    TIME NULL,
        time_end      TIME NULL,
        created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lists_team_id    ON {$s}.lists(team_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lists_visibility ON {$s}.lists(visibility)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.columns (
        id         SERIAL PRIMARY KEY,
        team_id    INTEGER REFERENCES {$s}.teams(id) ON DELETE CASCADE,
        list_id    INTEGER REFERENCES {$s}.lists(id) ON DELETE CASCADE,
        name       VARCHAR(100) NOT NULL,
        data_type  VARCHAR(10) NOT NULL CHECK (data_type IN ('boolean', 'number', 'text')),
        is_active  BOOLEAN NOT NULL DEFAULT TRUE,
        sort_order INTEGER NOT NULL DEFAULT 0,
        coach_only BOOLEAN NOT NULL DEFAULT FALSE,
        is_system  BOOLEAN NOT NULL DEFAULT FALSE,
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

    // Note: member_id has no FK to users — it also stores free_list_rows.id for free lists.
    // The app layer enforces ownership; RLS enforces row visibility.
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.cells (
        id         SERIAL PRIMARY KEY,
        list_id    INTEGER NOT NULL REFERENCES {$s}.lists(id)    ON DELETE CASCADE,
        column_id  INTEGER NOT NULL REFERENCES {$s}.columns(id)  ON DELETE CASCADE,
        member_id  INTEGER NOT NULL,
        value      TEXT,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        UNIQUE (list_id, column_id, member_id)
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_list_id   ON {$s}.cells(list_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_column_id ON {$s}.cells(column_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cells_member_id ON {$s}.cells(member_id)");

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

    // ── Phase 8: Member & Club Management ─────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.clubs (
        id         SERIAL PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        is_active  BOOLEAN NOT NULL DEFAULT TRUE,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.member_attribute_groups (
        id         SERIAL PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.members (
        id           SERIAL PRIMARY KEY,
        club_id      INTEGER REFERENCES {$s}.clubs(id) ON DELETE SET NULL,
        first_name   VARCHAR(100) NOT NULL,
        last_name    VARCHAR(100) NOT NULL,
        email        VARCHAR(255) NULL,
        description  TEXT NULL,
        phone        VARCHAR(50) NULL,
        contact_name  VARCHAR(100) NULL,
        contact_phone VARCHAR(50)  NULL,
        contact_email VARCHAR(254) NULL,
        is_active     BOOLEAN NOT NULL DEFAULT TRUE,
        confirmed_at  TIMESTAMPTZ NULL,
        created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_members_club ON {$s}.members(club_id)");

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.member_attributes (
        id                 SERIAL PRIMARY KEY,
        group_id           INTEGER NOT NULL REFERENCES {$s}.member_attribute_groups(id) ON DELETE CASCADE,
        name               VARCHAR(100) NOT NULL,
        data_type          VARCHAR(10)  NOT NULL DEFAULT 'text'
                           CHECK (data_type IN ('text', 'date')),
        visible_to_player  BOOLEAN NOT NULL DEFAULT TRUE,
        editable_by_player BOOLEAN NOT NULL DEFAULT FALSE,
        sort_order         INTEGER NOT NULL DEFAULT 0,
        created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ma_group ON {$s}.member_attributes(group_id)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.member_attribute_values (
        id           SERIAL PRIMARY KEY,
        member_id    INTEGER NOT NULL REFERENCES {$s}.members(id) ON DELETE CASCADE,
        attribute_id INTEGER NOT NULL REFERENCES {$s}.member_attributes(id) ON DELETE CASCADE,
        value        TEXT NOT NULL DEFAULT '',
        updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        UNIQUE (member_id, attribute_id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mav_member ON {$s}.member_attribute_values(member_id)");

    // users.member_id links a user account to the canonical member record (added after members table)
    $pdo->exec("ALTER TABLE {$s}.users
        ADD COLUMN IF NOT EXISTS member_id INTEGER REFERENCES {$s}.members(id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE {$s}.users
        ADD COLUMN IF NOT EXISTS club_id INTEGER REFERENCES {$s}.clubs(id) ON DELETE SET NULL");

    // files — Markdown documents visible to team members
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.files (
        id         SERIAL PRIMARY KEY,
        team_id    INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
        name       VARCHAR(255) NOT NULL,
        content    TEXT NULL,
        visibility VARCHAR(10)  NOT NULL DEFAULT 'public'
                   CHECK (visibility IN ('public', 'protected', 'private')),
        is_hidden  BOOLEAN NOT NULL DEFAULT FALSE,
        date       DATE NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_team_id ON {$s}.files(team_id)");

    // events — calendar events per team (ICS export, optional VALARM reminder)
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$s}.events (
        id          SERIAL PRIMARY KEY,
        team_id     INTEGER NOT NULL REFERENCES {$s}.teams(id) ON DELETE CASCADE,
        title       VARCHAR(200) NOT NULL,
        description TEXT NULL,
        location    VARCHAR(255) NULL,
        icon        VARCHAR(50)  NULL DEFAULT 'bi-calendar-event',
        is_hidden   BOOLEAN NOT NULL DEFAULT TRUE,
        date        DATE NOT NULL,
        is_all_day  BOOLEAN NOT NULL DEFAULT TRUE,
        time_start  TIME NULL,
        time_end    TIME NULL,
        visibility  VARCHAR(10) NOT NULL DEFAULT 'protected'
                    CHECK (visibility IN ('protected', 'private')),
        created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_team_id ON {$s}.events(team_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_date    ON {$s}.events(date)");
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

    $pdo->exec("CREATE POLICY columns_update ON {$s}.columns FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            AND (is_system IS NULL OR is_system = FALSE))
    ) WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
            AND (is_system IS NULL OR is_system = FALSE))
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

    $pdo->exec("CREATE POLICY lgc_update ON {$s}.list_global_columns FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = list_global_columns.list_id
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    ) WITH CHECK (
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
            AND member_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            AND EXISTS (SELECT 1 FROM {$s}.lists
                        WHERE lists.id = cells.list_id
                        AND lists.visibility = 'public'
                        AND lists.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer))
    )");

    $pdo->exec("CREATE POLICY cells_ownership_update ON {$s}.cells FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (current_setting('app.current_role', true) = 'member'
            AND member_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
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

    // ── Phase 8: Member & Club Management RLS ─────────────────────────────
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

    $pdo->exec("ALTER TABLE {$s}.member_attribute_groups ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.member_attribute_groups FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS member_attribute_groups skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY mag_select ON {$s}.member_attribute_groups FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR NULLIF(current_setting('app.current_team_id', true), '') IS NOT NULL
    )");
    $pdo->exec("CREATE POLICY mag_insert ON {$s}.member_attribute_groups FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY mag_update ON {$s}.member_attribute_groups FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY mag_delete ON {$s}.member_attribute_groups FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    $pdo->exec("ALTER TABLE {$s}.members ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.members FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS members skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY members_select ON {$s}.members FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (
            current_setting('app.current_role', true) IN ('coordinator', 'member')
            AND EXISTS (
                SELECT 1 FROM {$s}.users u
                WHERE u.member_id = members.id
                  AND u.team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
                  AND u.role = 'member'
            )
        )
        OR EXISTS (
            SELECT 1 FROM {$s}.users u
            WHERE u.member_id = members.id
              AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
        )
    )");
    $pdo->exec("CREATE POLICY members_insert ON {$s}.members FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY members_update ON {$s}.members FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY members_delete ON {$s}.members FOR DELETE USING (
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

    $pdo->exec("ALTER TABLE {$s}.member_attributes ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.member_attributes FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS member_attributes skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY ma_select ON {$s}.member_attributes FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND visible_to_player = TRUE
        )
    )");
    $pdo->exec("CREATE POLICY ma_insert ON {$s}.member_attributes FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY ma_update ON {$s}.member_attributes FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
    )");
    $pdo->exec("CREATE POLICY ma_delete ON {$s}.member_attributes FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    $pdo->exec("ALTER TABLE {$s}.member_attribute_values ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.member_attribute_values FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS member_attribute_values skipped (non-fatal) — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY mav_select ON {$s}.member_attribute_values FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$s}.users u
                WHERE u.member_id = member_attribute_values.member_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$s}.member_attributes ma
                WHERE ma.id = member_attribute_values.attribute_id
                  AND ma.visible_to_player = TRUE
            )
        )
    )");
    $pdo->exec("CREATE POLICY mav_insert ON {$s}.member_attribute_values FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$s}.users u
                WHERE u.member_id = member_attribute_values.member_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$s}.member_attributes ma
                WHERE ma.id = member_attribute_values.attribute_id
                  AND ma.editable_by_player = TRUE
            )
        )
    )");
    $pdo->exec("CREATE POLICY mav_update ON {$s}.member_attribute_values FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR current_setting('app.current_role', true) = 'coordinator'
        OR (
            current_setting('app.current_role', true) = 'member'
            AND EXISTS (
                SELECT 1 FROM {$s}.users u
                WHERE u.member_id = member_attribute_values.member_id
                  AND u.id = NULLIF(current_setting('app.current_user_id', true), '')::integer
            )
            AND EXISTS (
                SELECT 1 FROM {$s}.member_attributes ma
                WHERE ma.id = member_attribute_values.attribute_id
                  AND ma.editable_by_player = TRUE
            )
        )
    )");

    // users DELETE: only admin can delete users
    $pdo->exec("CREATE POLICY team_isolation_users_delete ON {$s}.users FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
    )");

    // ── files RLS ─────────────────────────────────────────────────────────────
    $pdo->exec("ALTER TABLE {$s}.files ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.files FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS files skipped — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY files_select ON {$s}.files FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
        OR (visibility IN ('public', 'protected')
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");
    $pdo->exec("CREATE POLICY files_insert ON {$s}.files FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");
    $pdo->exec("CREATE POLICY files_update ON {$s}.files FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");
    $pdo->exec("CREATE POLICY files_delete ON {$s}.files FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");

    // ── events RLS ────────────────────────────────────────────────────────────
    $pdo->exec("ALTER TABLE {$s}.events ENABLE ROW LEVEL SECURITY");
    try {
        $pdo->exec("ALTER TABLE {$s}.events FORCE ROW LEVEL SECURITY");
    } catch (PDOException $e) {
        error_log('db_init_rls: FORCE RLS events skipped — ' . $e->getMessage());
    }
    $pdo->exec("CREATE POLICY events_select ON {$s}.events FOR SELECT USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
        OR (visibility = 'protected'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");
    $pdo->exec("CREATE POLICY events_insert ON {$s}.events FOR INSERT WITH CHECK (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");
    $pdo->exec("CREATE POLICY events_update ON {$s}.events FOR UPDATE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
    )");
    $pdo->exec("CREATE POLICY events_delete ON {$s}.events FOR DELETE USING (
        current_setting('app.is_admin', true) = 'true'
        OR (current_setting('app.current_role', true) = 'coordinator'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer)
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
