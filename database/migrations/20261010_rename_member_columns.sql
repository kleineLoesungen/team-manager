-- database/migrations/20261010_rename_member_columns.sql
-- Team Manager — Migration: Spalten „player“ → „member“ (Aufräumen, Begriffe wie in der App)
--
-- VOR dem Deployment einspielen (Reihenfolge und Anleitung: Admin → Einstellungen → Version).
-- member_attributes.visible_to_player  → visible_to_member
-- member_attributes.editable_by_player → editable_by_member
-- Nur Umbenennungen, keine Daten ändern sich. Die Row-Level-Security-Richtlinien folgen
-- automatisch (PostgreSQL führt sie über die Spalte, nicht über ihren Namen).
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Mehrfach ausführbar.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der config.php der Instanz
SET LOCAL app.is_admin = 'true';             -- Row-Level Security (settings)

DO $$
BEGIN
    IF to_regclass('member_attributes') IS NULL OR to_regclass('settings') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen member_attributes/settings nicht gefunden)';
    END IF;
    -- Nur umbenennen, solange es die alten Namen noch gibt
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'member_attributes'
                 AND column_name = 'visible_to_player') THEN
        ALTER TABLE member_attributes RENAME COLUMN visible_to_player TO visible_to_member;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'member_attributes'
                 AND column_name = 'editable_by_player') THEN
        ALTER TABLE member_attributes RENAME COLUMN editable_by_player TO editable_by_member;
    END IF;
END $$;

-- Stand der Datenbank: diese Migration ist eingespielt
INSERT INTO settings (key, value) VALUES ('db_migration', '20261010_rename_member_columns')
ON CONFLICT (key) DO UPDATE SET value = GREATEST(settings.value, EXCLUDED.value);

COMMIT;
