---
id: 260501-ahg
phase: quick
plan: 260501-ahg
subsystem: deployment / db-init / rls
tags: [hetzner, ftp, rls, db-self-init, deployment]
dependency_graph:
  requires: []
  provides: [hetzner-ftp-deploy, db-self-init, rls-force-owner]
  affects: [public/index.php, src/db/connection.php, database/rls_policies.sql, deploy.sh, README.md]
tech_stack:
  added: [lftp]
  patterns: [ROOT_PATH auto-detection, idempotent DB init via to_regclass, FORCE ROW LEVEL SECURITY]
key_files:
  created: []
  modified:
    - public/index.php
    - src/db/connection.php
    - database/rls_policies.sql
    - deploy.sh
    - README.md
decisions:
  - ROOT_PATH conditional on is_dir src/ sibling — handles both dev layout and Hetzner public_html/team-manager/ webroot
  - maybe_init_db uses to_regclass (not information_schema) — single SQL round-trip, schema-aware, null means not exists
  - FORCE ROW LEVEL SECURITY on all 5 tables — Hetzner single DB user is table owner; ENABLE alone doesn't apply policies to owner
  - config.php excluded from lftp mirror — credentials persist across deployments without manual intervention
metrics:
  duration: ~10 minutes
  completed_date: "2026-05-01"
  tasks_completed: 4
  files_changed: 5
---

# Quick Task 260501-ahg: Hetzner Shared Hosting Summary

**One-liner:** FTP deploy via lftp with ROOT_PATH auto-detection, idempotent DB self-init on first request, and FORCE RLS for single-owner PostgreSQL setup.

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | ROOT_PATH auto-detection in public/index.php | 6bf4472 |
| 2 | DB self-init (maybe_init_db) in src/db/connection.php | 3783973 |
| 3 | FORCE ROW LEVEL SECURITY in database/rls_policies.sql | f87ccb1 |
| 4 | deploy.sh FTP rewrite + README Hetzner section | 5945bf7 |

## What Was Built

### Task 1 — ROOT_PATH auto-detection

`public/index.php` now detects whether it is running in development (parent directory contains `src/`) or on Hetzner (no `src/` sibling — webroot is `public_html/team-manager/`). In the Hetzner case ROOT_PATH resolves to `~/apps/team-manager`.

### Task 2 — DB self-init on first request

`maybe_init_db(PDO $pdo)` is called inside `get_db()` before returning the connection. It queries `to_regclass('schema.teams')` — if null, the schema hasn't been created yet, and it runs `schema.sql` then `rls_policies.sql` automatically. Subsequent requests return immediately (null check passes, function exits).

### Task 3 — FORCE ROW LEVEL SECURITY

On Hetzner the single DB user owns the tables. PostgreSQL's `ENABLE ROW LEVEL SECURITY` doesn't apply policies to the table owner by default. `FORCE ROW LEVEL SECURITY` overrides this. Added after every ENABLE line for all 5 tables: `users`, `lists`, `columns`, `cells`, `list_global_columns`.

### Task 4 — FTP deploy script + README

`deploy.sh` rewritten to use `lftp` with two `mirror --reverse` operations:
- `public/` → `public_html/team-manager/` (with `--delete` to clean stale files)
- repo root → `apps/team-manager/` (without `--delete` to preserve server-side `config.php`)

`config.php` is explicitly excluded from the mirror. README deployment section replaced with Hetzner-specific instructions covering subdomain setup, one-time config.php creation, and follow-up deploys.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

All 5 modified files confirmed present. All 4 task commits confirmed in git log.
