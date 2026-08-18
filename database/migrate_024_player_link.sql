-- migrate_024_player_link.sql
-- One-time production migration: create player records for all users without one,
-- then enforce users.player_id NOT NULL.
--
-- Run BEFORE deploying the new application code:
--   psql -U <dbuser> -d <dbname> -f migrate_024_player_link.sql
--
-- Safe to run multiple times (the DO block only processes users where player_id IS NULL).

SET search_path TO manager, public;
SET app.is_admin = 'true';

DO $$
DECLARE
    u   RECORD;
    pid INT;
BEGIN
    FOR u IN
        SELECT id, first_name, last_name, email, phone, club_id, created_at
        FROM manager.users
        WHERE player_id IS NULL
    LOOP
        INSERT INTO manager.players (first_name, last_name, email, phone, club_id, created_at)
        VALUES (u.first_name, u.last_name, u.email, u.phone, u.club_id, u.created_at)
        RETURNING id INTO pid;

        UPDATE manager.users SET player_id = pid WHERE id = u.id;
    END LOOP;
END $$;

-- Enforce the constraint (no-op if already set)
ALTER TABLE manager.users ALTER COLUMN player_id SET NOT NULL;

RESET app.is_admin;
