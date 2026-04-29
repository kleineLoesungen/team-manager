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
