-- database/migrations/20261004_list_auto_visibility.sql
-- Team Manager — Einmal-Migration: zeitgesteuerte Sichtbarkeit von Listen
--
-- VOR dem Deployment der App-Version mit "Sichtbarkeit automatisch umstellen" einspielen,
-- danach diese Datei löschen (Konvention: keine Migrationsdateien im Repo, siehe README).
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert;
-- bei jedem Fehler wird die ganze Transaktion zurückgerollt.
-- Rechte: DDL — als Eigentümer-Rolle der Tabelle lists ausführen. Idempotent.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der Produktions-config.php

DO $$
BEGIN
    IF to_regclass('lists') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabelle lists nicht gefunden)';
    END IF;
END $$;

ALTER TABLE lists ADD COLUMN IF NOT EXISTS auto_visibility VARCHAR(10) NULL
    CHECK (auto_visibility IN ('public', 'protected', 'private'));
ALTER TABLE lists ADD COLUMN IF NOT EXISTS auto_visibility_hours INTEGER NOT NULL DEFAULT 0
    CHECK (auto_visibility_hours BETWEEN 0 AND 720);
ALTER TABLE lists ADD COLUMN IF NOT EXISTS auto_visibility_done_at TIMESTAMPTZ NULL;

CREATE INDEX IF NOT EXISTS idx_lists_auto_visibility_pending ON lists(date)
    WHERE auto_visibility IS NOT NULL AND auto_visibility_done_at IS NULL;

COMMIT;
