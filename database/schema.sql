-- Team Manager — Database Schema
-- PostgreSQL 14+
-- Run as superuser. DB_SCHEMA isolates this app from others in the same database.

CREATE SCHEMA IF NOT EXISTS team_manager;
SET search_path TO team_manager, public;

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Users table — coaches and players only (admin is in config.php, per D-02)
CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    team_id       INTEGER REFERENCES teams(id) ON DELETE SET NULL,
    role          VARCHAR(10) NOT NULL CHECK (role IN ('coach', 'player')),
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_team_id  ON users(team_id);

-- ── Phase 3: Lists, Columns & Cells ──────────────────────────────────────────

-- Lists — one per team/coach usage; has visibility state
CREATE TABLE IF NOT EXISTS lists (
    id          SERIAL PRIMARY KEY,
    team_id     INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL,
    visibility  VARCHAR(10)  NOT NULL DEFAULT 'public'
                CHECK (visibility IN ('public', 'protected', 'private')),
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_lists_team_id   ON lists(team_id);
CREATE INDEX IF NOT EXISTS idx_lists_visibility ON lists(visibility);

-- Columns — attribute metadata for EAV (global: list_id IS NULL; local: list_id IS NOT NULL)
-- Global columns: data_type IN ('boolean', 'number') only — text NOT allowed for global (STAT requirement)
-- Local columns: data_type IN ('boolean', 'number', 'text')
CREATE TABLE IF NOT EXISTS columns (
    id          SERIAL PRIMARY KEY,
    team_id     INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    list_id     INTEGER REFERENCES lists(id) ON DELETE CASCADE,
    -- list_id IS NULL => global column (belongs to team); list_id IS NOT NULL => local column (belongs to list)
    name        VARCHAR(100) NOT NULL,
    data_type   VARCHAR(10)  NOT NULL
                CHECK (data_type IN ('boolean', 'number', 'text')),
    -- Application layer must enforce: data_type='text' only when list_id IS NOT NULL
    is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
    sort_order  INTEGER      NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_columns_team_id  ON columns(team_id);
CREATE INDEX IF NOT EXISTS idx_columns_list_id  ON columns(list_id);

-- Cells — EAV values (value stored as TEXT; parsed by app layer per column.data_type)
CREATE TABLE IF NOT EXISTS cells (
    id          SERIAL PRIMARY KEY,
    list_id     INTEGER NOT NULL REFERENCES lists(id)   ON DELETE CASCADE,
    column_id   INTEGER NOT NULL REFERENCES columns(id) ON DELETE CASCADE,
    player_id   INTEGER NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    value       TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (list_id, column_id, player_id)
);
CREATE INDEX IF NOT EXISTS idx_cells_list_id    ON cells(list_id);
CREATE INDEX IF NOT EXISTS idx_cells_column_id  ON cells(column_id);
CREATE INDEX IF NOT EXISTS idx_cells_player_id  ON cells(player_id);
