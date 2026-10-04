-- database/migrations/20261004_list_default_value.sql
-- Team Manager — Einmal-Migration: Standardwert je Liste und globaler Spalte merken
--
-- VOR dem Deployment einspielen, danach diese Datei löschen (Konvention, siehe README).
-- Damit bekommen später hinzukommende Mitglieder in Listen ab heute den Standardwert,
-- der beim Anlegen der Liste gesetzt wurde. Bestehende Listen bleiben ohne gespeicherten
-- Standardwert (NULL) und verhalten sich wie bisher.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der Tabelle list_global_columns. Idempotent.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der Produktions-config.php

DO $$
BEGIN
    IF to_regclass('list_global_columns') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabelle list_global_columns nicht gefunden)';
    END IF;
END $$;

ALTER TABLE list_global_columns ADD COLUMN IF NOT EXISTS default_value TEXT NULL;

COMMIT;
