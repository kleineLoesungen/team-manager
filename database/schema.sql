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
    logo_path   VARCHAR(500)         NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Users table — coordinators and members only (admin is in config.php, per D-02)
CREATE TABLE IF NOT EXISTS team_manager.users (
    id            SERIAL PRIMARY KEY,
    team_id       INTEGER REFERENCES team_manager.teams(id) ON DELETE SET NULL,
    role          VARCHAR(20) NOT NULL CHECK (role IN ('coordinator', 'member')),
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email         VARCHAR(255)     NULL,
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_username ON team_manager.users(username);
CREATE INDEX IF NOT EXISTS idx_users_team_id  ON team_manager.users(team_id);

-- Migration for existing databases (Phase 5 — email notifications):
-- ALTER TABLE team_manager.users ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL;
-- (No DB-level CHECK constraint — application validates via filter_var(FILTER_VALIDATE_EMAIL))

-- ── Settings ──────────────────────────────────────────────────────────────────

-- Global app settings (key/value pairs)
CREATE TABLE IF NOT EXISTS team_manager.settings (
    key   VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
INSERT INTO team_manager.settings (key, value) VALUES ('app_title', 'Team Manager') ON CONFLICT DO NOTHING;
INSERT INTO team_manager.settings (key, value) VALUES ('default_team_logo', '') ON CONFLICT DO NOTHING;

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
    location      VARCHAR(255)             NULL,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
-- Migration for existing databases:
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS show_all_rows BOOLEAN NOT NULL DEFAULT FALSE;
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS is_hidden     BOOLEAN NOT NULL DEFAULT FALSE;
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS description TEXT NULL;
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS date DATE NULL;
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL;
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

-- ── Phase 7: Live-Ticker ─────────────────────────────────────────────────────

-- Ticker-Tags — team-wide configurable tags for messages
CREATE TABLE IF NOT EXISTS team_manager.ticker_tags (
    id         SERIAL PRIMARY KEY,
    team_id    INTEGER NOT NULL REFERENCES team_manager.teams(id) ON DELETE CASCADE,
    label      VARCHAR(50) NOT NULL,
    color      VARCHAR(20) NOT NULL DEFAULT 'secondary',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ticker_tags_team ON team_manager.ticker_tags(team_id);

-- Tickers — one ticker per event (tournament, game, etc.)
CREATE TABLE IF NOT EXISTS team_manager.tickers (
    id          SERIAL PRIMARY KEY,
    team_id     INTEGER NOT NULL REFERENCES team_manager.teams(id) ON DELETE CASCADE,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    status      VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'closed')),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_tickers_team_status ON team_manager.tickers(team_id, status);

-- Ticker-Messages — short messages posted to a ticker
CREATE TABLE IF NOT EXISTS team_manager.ticker_messages (
    id         SERIAL PRIMARY KEY,
    ticker_id  INTEGER NOT NULL REFERENCES team_manager.tickers(id) ON DELETE CASCADE,
    tag_id     INTEGER REFERENCES team_manager.ticker_tags(id) ON DELETE SET NULL,
    message    VARCHAR(280) NOT NULL,
    timestamp  VARCHAR(5) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ticker_messages_ticker_created ON team_manager.ticker_messages(ticker_id, created_at DESC);

-- Ticker-Members — join table: which members may post to which ticker
CREATE TABLE IF NOT EXISTS team_manager.ticker_members (
    ticker_id INTEGER NOT NULL REFERENCES team_manager.tickers(id) ON DELETE CASCADE,
    user_id   INTEGER NOT NULL REFERENCES team_manager.users(id) ON DELETE CASCADE,
    team_id   INTEGER NOT NULL REFERENCES team_manager.teams(id) ON DELETE CASCADE,
    PRIMARY KEY (ticker_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_ticker_members_user ON team_manager.ticker_members(user_id, team_id);

-- ── Phase 8: Player & Club Management ─────────────────────────────────────────

-- Clubs — permanent home of players, independent of team assignments
CREATE TABLE IF NOT EXISTS team_manager.clubs (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    is_active  BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Player attribute groups — admin-defined groupings for dynamic player attributes (e.g. "Kontakt", "Spielerprofil")
CREATE TABLE IF NOT EXISTS team_manager.player_attribute_groups (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Players — permanent identity layer, separate from users accounts
-- A player may or may not have a linked users account (users.player_id is the FK)
CREATE TABLE IF NOT EXISTS team_manager.players (
    id           SERIAL PRIMARY KEY,
    club_id      INTEGER REFERENCES team_manager.clubs(id) ON DELETE SET NULL,
    first_name   VARCHAR(100) NOT NULL,
    last_name    VARCHAR(100) NOT NULL,
    description  TEXT NULL,
    phone        VARCHAR(50) NULL,
    contact_name VARCHAR(100) NULL,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_players_club ON team_manager.players(club_id);

-- Coordinator-teams — multi-team coordinator membership (replaces single users.team_id conceptually)
-- users.team_id is KEPT and synced to active session team to preserve existing RLS pattern
-- left_at IS NULL means currently active; partial unique index enforces one active entry per (user, team) pair
CREATE TABLE IF NOT EXISTS team_manager.coordinator_teams (
    id        SERIAL PRIMARY KEY,
    user_id   INTEGER NOT NULL REFERENCES team_manager.users(id) ON DELETE CASCADE,
    team_id   INTEGER NOT NULL REFERENCES team_manager.teams(id) ON DELETE CASCADE,
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    left_at   TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS idx_ct_user ON team_manager.coordinator_teams(user_id);
CREATE INDEX IF NOT EXISTS idx_ct_team ON team_manager.coordinator_teams(team_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_ct_active ON team_manager.coordinator_teams(user_id, team_id) WHERE left_at IS NULL;

-- Player-attributes — EAV attribute definitions within groups
-- visible_to_player: member can see this value; editable_by_player: member can edit it
CREATE TABLE IF NOT EXISTS team_manager.player_attributes (
    id                 SERIAL PRIMARY KEY,
    group_id           INTEGER NOT NULL REFERENCES team_manager.player_attribute_groups(id) ON DELETE CASCADE,
    name               VARCHAR(100) NOT NULL,
    visible_to_player  BOOLEAN NOT NULL DEFAULT TRUE,
    editable_by_player BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order         INTEGER NOT NULL DEFAULT 0,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_pa_group ON team_manager.player_attributes(group_id);

-- Player-attribute-values — EAV values (player_id × attribute_id → TEXT value)
-- ON CONFLICT (player_id, attribute_id) DO UPDATE for upsert pattern
CREATE TABLE IF NOT EXISTS team_manager.player_attribute_values (
    id           SERIAL PRIMARY KEY,
    player_id    INTEGER NOT NULL REFERENCES team_manager.players(id) ON DELETE CASCADE,
    attribute_id INTEGER NOT NULL REFERENCES team_manager.player_attributes(id) ON DELETE CASCADE,
    value        TEXT NOT NULL DEFAULT '',
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (player_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_pav_player ON team_manager.player_attribute_values(player_id);

-- Migration for existing databases (Phase 8 — player & club management):
-- ALTER TABLE team_manager.users ADD COLUMN IF NOT EXISTS player_id INTEGER REFERENCES team_manager.players(id) ON DELETE SET NULL;
-- ALTER TABLE team_manager.users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL;
