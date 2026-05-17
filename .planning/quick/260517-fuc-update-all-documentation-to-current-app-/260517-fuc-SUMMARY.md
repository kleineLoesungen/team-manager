---
phase: quick
plan: 260517-fuc
subsystem: documentation
tags: [docs, claude-md, readme, conventions, architecture, roles]
dependency_graph:
  requires: []
  provides: [accurate-claude-md, accurate-readme]
  affects: []
tech_stack:
  added: []
  patterns: []
key_files:
  created: []
  modified:
    - CLAUDE.md
    - README.md
decisions:
  - "Kept 'Spielereinsatz' compound word in Core Value — it describes the tracked metric, not a role"
  - "Conventions documented from 34+ quick task patterns — roles, routing, security, UI, DB, deployment"
metrics:
  duration: "5 minutes"
  completed: "2026-05-17"
  tasks_completed: 2
  files_modified: 2
---

# Quick Task 260517-fuc: Update All Documentation to Current App State

**One-liner:** Updated CLAUDE.md and README.md to replace stale Trainer/Spieler role references with Koordinator/Mitglied and added real conventions and architecture documentation derived from 34+ completed quick tasks.

## Tasks Completed

### Task 1: Update CLAUDE.md — project description, conventions, architecture
**Commit:** 93ff238

**Changes made:**

1. **Project description** — Replaced "Trainer" with "Koordinatoren" and "Spieler" with "Mitglieder" throughout the `<!-- GSD:project-start -->` block. Updated Core Value sentence to use "Koordinatoren" and "Mitglied".

2. **Conventions section** — Replaced placeholder "Conventions not yet established." with 7 documented convention groups:
   - Roles (DB values, German UI labels, Du-speech)
   - File / Folder Structure (handler locations, template mirroring, utils, db)
   - Routing (front controller, dispatch pattern, PRG)
   - Security (CSRF, require_* guards, triple-constraint ownership, credential modal)
   - Templates (layout files per role, Bootstrap 5.3 CDN)
   - UI Patterns (form-switch toggles, two-step confirm, scroll restore)
   - Database (set_team_context RLS, EAV pattern, settings table, no migration files)
   - Deployment (deploy.sh, config.php protection, uploads/.htaccess)

3. **Architecture section** — Replaced placeholder "Architecture not yet mapped." with:
   - Full directory layout tree (public/, src/ with all subdirs, database/, docker/, landing/, uploads/)
   - Request flow diagram (Browser → index.php → handler → template → PRG)
   - Key DB tables reference (9 tables: teams, users, settings, lists, columns, list_global_columns, cells, files, free_list_rows)

### Task 2: Update README.md — project structure, login table, DB section
**Commit:** 99d9712

**Changes made:**

1. **Login table** — Added Koordinator and Mitglied rows below the Admin row, with clear German descriptions of how credentials are created.

2. **Database section** — Added "Hinweis zu selbst-initialisierenden Tabellen" note explaining that `files` and `free_list_rows` are created via self-init in handlers (not schema.sql), and that `list_type` and `brand_color` columns were added via migration.

3. **Projektstruktur** — Replaced the outdated 7-line structure with a complete 20-line layout reflecting:
   - `coordinator/` and `member/` handler directories (previously missing)
   - `templates/` with role-specific subdirs and explicit file names
   - `utils/` with explicit file names (csrf.php, helpers.php)
   - `landing/` and `uploads/` directories (previously missing)
   - `deploy.sh` entry (previously missing)

## Deviations from Plan

None — plan executed exactly as written.

The grep check "Trainer|Spieler|Coach|coach" matched two intentional occurrences:
- "Spielereinsatz" (German compound noun meaning player-deployment, the correct metric term)
- "coach/player/moderator" in Conventions documenting deprecated role value names to avoid

Both are correct and expected.

## Self-Check: PASSED

- CLAUDE.md: "Koordinator" appears 3 times (project desc, conventions, architecture) — correct
- CLAUDE.md: "not yet established" appears 0 times — correct
- CLAUDE.md: "src/coordinator/" appears in architecture section — correct
- README.md: "Koordinator" appears 7 times (login table, structure section) — correct
- README.md: "coordinator/" appears 2 times (structure section) — correct
- README.md: "files" and "free_list_rows" mentioned in DB note — correct
- Commits 93ff238 and 99d9712 both exist in git log
