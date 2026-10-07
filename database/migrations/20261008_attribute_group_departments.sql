-- database/migrations/20261008_attribute_group_departments.sql
-- Team Manager — Einmal-Migration: Attributgruppen je Abteilung
--
-- VOR dem Deployment einspielen, danach diese Datei löschen (Konvention, siehe README).
-- Neue Spalte member_attribute_groups.department_id. Bestehende Gruppen bleiben ohne
-- Abteilung und gelten damit weiter für alle. Daten werden nicht verändert.
--
-- pgAdmin: Schema in der Zeile `SET LOCAL search_path` eintragen, dann das ganze Skript
-- ausführen (F5). Reines SQL. Bei falschem Schema bricht es ab, bevor es etwas ändert.
-- Rechte: DDL — als Eigentümer-Rolle der App-Tabellen. Idempotent.

BEGIN;

SET LOCAL search_path TO SCHEMA_EINTRAGEN;   -- <<< DB_SCHEMA aus der Produktions-config.php

DO $$
BEGIN
    IF to_regclass('member_attribute_groups') IS NULL OR to_regclass('departments') IS NULL THEN
        RAISE EXCEPTION 'search_path zeigt nicht auf das App-Schema (Tabellen member_attribute_groups/departments nicht gefunden)';
    END IF;
END $$;

ALTER TABLE member_attribute_groups
    ADD COLUMN IF NOT EXISTS department_id INTEGER NULL REFERENCES departments(id);

COMMIT;
