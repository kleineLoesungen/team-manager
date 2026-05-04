-- Team Manager — Database Schema
-- PostgreSQL 14+
-- All table/index names are schema-qualified so IF NOT EXISTS checks are
-- scoped to team_manager only, not the full search_path.

CREATE SCHEMA IF NOT EXISTS team_manager;
SET search_path TO team_manager, public;

-- Teams table
CREATE TABLE IF NOT EXISTS team_manager.teams (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Users table — coaches and players only (admin is in config.php, per D-02)
CREATE TABLE IF NOT EXISTS team_manager.users (
    id            SERIAL PRIMARY KEY,
    team_id       INTEGER REFERENCES team_manager.teams(id) ON DELETE SET NULL,
    role          VARCHAR(10) NOT NULL CHECK (role IN ('moderator', 'mitglied')),
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_username ON team_manager.users(username);
CREATE INDEX IF NOT EXISTS idx_users_team_id  ON team_manager.users(team_id);

-- ── Settings ──────────────────────────────────────────────────────────────────

-- Global app settings (key/value pairs)
CREATE TABLE IF NOT EXISTS team_manager.settings (
    key   VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
INSERT INTO team_manager.settings (key, value) VALUES ('app_title', 'Team Manager') ON CONFLICT DO NOTHING;

-- ── Phase 3: Lists, Columns & Cells ──────────────────────────────────────────

-- Lists — one per team/coach usage; has visibility state
-- show_all_rows: when TRUE players see all rows; when FALSE players see only their own row
-- is_hidden: when TRUE list is collapsed at bottom of overview (coach + player); content still accessible
CREATE TABLE IF NOT EXISTS team_manager.lists (
    id            SERIAL PRIMARY KEY,
    team_id       INTEGER NOT NULL REFERENCES team_manager.teams(id) ON DELETE CASCADE,
    name          VARCHAR(100) NOT NULL,
    visibility    VARCHAR(10)  NOT NULL DEFAULT 'public'
                  CHECK (visibility IN ('public', 'protected', 'private')),
    show_all_rows BOOLEAN      NOT NULL DEFAULT FALSE,
    is_hidden     BOOLEAN      NOT NULL DEFAULT FALSE,
    description   TEXT                     NULL,
    date          DATE                     NULL,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
-- Migration for existing databases:
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS show_all_rows BOOLEAN NOT NULL DEFAULT FALSE;
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS is_hidden     BOOLEAN NOT NULL DEFAULT FALSE;
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS description TEXT NULL;
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS date DATE NULL;
CREATE INDEX IF NOT EXISTS idx_lists_team_id    ON team_manager.lists(team_id);
CREATE INDEX IF NOT EXISTS idx_lists_visibility ON team_manager.lists(visibility);

-- Columns — attribute metadata for EAV (global: list_id IS NULL; local: list_id IS NOT NULL)
-- Global columns: data_type IN ('boolean', 'number') only — text NOT allowed for global (STAT requirement)
-- Local columns: data_type IN ('boolean', 'number', 'text')
CREATE TABLE IF NOT EXISTS team_manager.columns (
    id          SERIAL PRIMARY KEY,
    team_id     INTEGER NOT NULL REFERENCES team_manager.teams(id) ON DELETE CASCADE,
    list_id     INTEGER REFERENCES team_manager.lists(id) ON DELETE CASCADE,
    -- list_id IS NULL => global column (belongs to team); list_id IS NOT NULL => local column (belongs to list)
    name        VARCHAR(100) NOT NULL,
    data_type   VARCHAR(10)  NOT NULL
                CHECK (data_type IN ('boolean', 'number', 'text')),
    -- Application layer must enforce: data_type='text' only when list_id IS NOT NULL
    is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
    sort_order  INTEGER      NOT NULL DEFAULT 0,
    coach_only  BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
-- Migration for existing databases:
-- ALTER TABLE columns ADD COLUMN IF NOT EXISTS coach_only BOOLEAN NOT NULL DEFAULT FALSE;
CREATE INDEX IF NOT EXISTS idx_columns_team_id  ON team_manager.columns(team_id);
CREATE INDEX IF NOT EXISTS idx_columns_list_id  ON team_manager.columns(list_id);

-- List–global-column associations — which global columns appear in each list (D-11)
-- A global column only shows in a list if a row exists here for that (list_id, column_id) pair.
CREATE TABLE IF NOT EXISTS team_manager.list_global_columns (
    list_id   INTEGER NOT NULL REFERENCES team_manager.lists(id)   ON DELETE CASCADE,
    column_id INTEGER NOT NULL REFERENCES team_manager.columns(id) ON DELETE CASCADE,
    PRIMARY KEY (list_id, column_id)
);
CREATE INDEX IF NOT EXISTS idx_lgc_list_id   ON team_manager.list_global_columns(list_id);
CREATE INDEX IF NOT EXISTS idx_lgc_column_id ON team_manager.list_global_columns(column_id);

-- Cells — EAV values (value stored as TEXT; parsed by app layer per column.data_type)
CREATE TABLE IF NOT EXISTS team_manager.cells (
    id          SERIAL PRIMARY KEY,
    list_id     INTEGER NOT NULL REFERENCES team_manager.lists(id)   ON DELETE CASCADE,
    column_id   INTEGER NOT NULL REFERENCES team_manager.columns(id) ON DELETE CASCADE,
    player_id   INTEGER NOT NULL REFERENCES team_manager.users(id)   ON DELETE CASCADE,
    value       TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (list_id, column_id, player_id)
);
CREATE INDEX IF NOT EXISTS idx_cells_list_id    ON team_manager.cells(list_id);
CREATE INDEX IF NOT EXISTS idx_cells_column_id  ON team_manager.cells(column_id);
CREATE INDEX IF NOT EXISTS idx_cells_player_id  ON team_manager.cells(player_id);
