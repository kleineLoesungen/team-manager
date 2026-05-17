---
phase: quick
plan: 260517-fuc
type: execute
wave: 1
depends_on: []
files_modified:
  - CLAUDE.md
  - README.md
autonomous: true
requirements: []
must_haves:
  truths:
    - "CLAUDE.md project description and role names match the live app (Koordinator, Mitglied)"
    - "CLAUDE.md Conventions section documents established patterns (not 'not yet established')"
    - "CLAUDE.md Architecture section documents actual file structure and role separation"
    - "README.md project structure reflects actual directories (coordinator/, member/, uploads/, landing/)"
    - "README.md login table includes Koordinator and Mitglied example rows"
    - "README.md database section reflects current schema (files, free_list_rows, list_type)"
  artifacts:
    - path: "CLAUDE.md"
      provides: "Updated project description, conventions, architecture"
    - path: "README.md"
      provides: "Updated project structure, login table, DB section"
  key_links: []
---

<objective>
Update CLAUDE.md and README.md to reflect the current app state after 34+ quick tasks have significantly evolved the codebase.

Purpose: Both docs were written before major changes — role renames (Trainer→Koordinator, Spieler→Mitglied), new features (markdown files, free lists, logo upload, landing page, Hetzner deploy), and established conventions. They are misleading to any reader including Claude itself.

Output: Accurate CLAUDE.md (project description + real conventions + real architecture) and README.md (correct structure, roles, DB tables).
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
</execution_context>

<context>
@.planning/STATE.md
@CLAUDE.md
@README.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Update CLAUDE.md — project description, conventions, architecture</name>
  <files>CLAUDE.md</files>
  <action>
Update three sections inside the GSD-managed comment blocks. Do NOT touch any other sections or remove any GSD comment markers.

**Section 1 — Project description** (`<!-- GSD:project-start -->` block):

Replace the project description text. Key changes:
- "Trainer" → "Koordinatoren" (plural) / "Koordinator" (singular)
- "Spieler" → "Mitglieder" (plural) / "Mitglied" (singular)
- Keep the structure (Project heading, paragraph, Core Value, Constraints)
- Core Value: "Koordinatoren können den Spielereinsatz und beliebige Kennzahlen über alle Listen hinweg erfassen und in einer Statistik pro Mitglied auf einen Blick auswerten."
- Project paragraph: "Koordinatoren legen Listen mit frei definierbaren Spalten an, Mitglieder tragen ihre eigenen Daten ein, und eine Statistikseite fasst die Kennzahlen pro Mitglied zusammen. Ein einziger Admin verwaltet Teams und Koordinatoren — alles andere regeln die Koordinatoren selbst."

**Section 2 — Conventions** (`<!-- GSD:conventions-start -->` block):

Replace "Conventions not yet established. Will populate as patterns emerge during development." with actual conventions derived from 34+ quick tasks:

```
## Conventions

### Roles
- DB role values: `coordinator` and `member` (not coach/player/moderator)
- German UI labels: "Koordinator" / "Mitglieder" / "Admin"
- Du-speech throughout the German UI

### File / Folder Structure
- Handler files live in `src/{role}/` (e.g. `src/coordinator/list_detail_handler.php`)
- Templates live in `src/templates/{role}/` mirroring handler paths
- `src/utils/` holds shared helpers: `csrf.php`, `helpers.php`
- `src/db/` holds PDO connection (`connection.php`) and visibility helpers (`visibility.php`)

### Routing
- Single front controller: `public/index.php`
- Routes dispatch to handler files in `src/{role}/`
- POST-redirect-GET pattern for all form submissions (error via `?error=` query param)

### Security
- CSRF token on every POST form — generate with `generate_csrf_token()`, validate with `validate_csrf_token()`
- `require_coordinator()` / `require_member()` called at top of every protected handler
- Triple-constraint ownership check on row edits: id + team_id + role
- Credentials shown via `credential_modal.php` (full-page include with `Cache-Control: no-store`)

### Templates
- `src/templates/layout.php` — shared login page layout
- `src/templates/admin/layout.php` — admin layout using `render_admin_page(callable $body)`
- `src/templates/coordinator/layout.php` — coordinator layout
- `src/templates/member/layout.php` — member layout
- Bootstrap 5.3 via CDN (no build step)

### UI Patterns
- Form switches (`form-switch`) for all boolean inputs (not plain checkboxes)
- Two-step confirm (Gefahrenzone card + confirmation page) for destructive actions — no JS required
- Scroll position restored via `sessionStorage` after GET filter forms submit

### Database
- `set_team_context()` called at session start — sets `app.current_role`, `app.current_user_id`, `app.current_team_id` for RLS
- EAV pattern: `columns` table (structure) + `cells` table (values); global columns have `list_id IS NULL`
- Settings stored in `settings` table as key/value pairs (e.g. `app_title`, `default_team_logo`)
- No migration files in repo — schema is idempotent via `IF NOT EXISTS`; live DB patched per-task then schema updated

### Deployment
- `deploy.sh` rsync script for Hetzner Shared Hosting
- `config.php` never overwritten by deploy (contains production secrets)
- `uploads/` directory holds team logos; `.htaccess` blocks direct HTTP access to files
```

**Section 3 — Architecture** (`<!-- GSD:architecture-start -->` block):

Replace "Architecture not yet mapped. Follow existing patterns found in the codebase." with:

```
## Architecture

### Directory Layout

```
public/             Webroot — index.php front controller + .htaccess
src/
  admin/            Admin handlers (teams, coordinators, settings)
  auth/             Login, logout, session
  coordinator/      Coordinator handlers (lists, columns, members, stats, files, logo)
  member/           Member handlers (lists, stats, files)
  db/               PDO connection + visibility helpers
  templates/
    admin/          Admin HTML templates
    coordinator/    Coordinator HTML templates
    member/         Member HTML templates
    layout.php      Shared login layout
    login.php       Login page
  utils/
    csrf.php        CSRF token generation + validation
    helpers.php     redirect(), htmle(), require_coordinator(), require_member() etc.
database/
  schema.sql        Idempotent schema (all tables)
  rls_policies.sql  Row-Level Security policies
docker/             Docker Compose setup for local dev
landing/            Static product landing page (not part of app)
uploads/            Logo uploads (HTTP-blocked via .htaccess)
config.php          App configuration (reads env vars)
deploy.sh           Hetzner FTP deploy script
```

### Request Flow

```
Browser → public/index.php (front controller)
  → parse URI → dispatch to src/{role}/{feature}_handler.php
  → handler: authenticate + CSRF check + business logic
  → render src/templates/{role}/{feature}.php
  → POST actions → redirect (PRG pattern)
```

### Key DB Tables

| Table | Purpose |
|-------|---------|
| `teams` | Teams with name, active flag, logo path |
| `users` | Coordinators and members (role = 'coordinator' or 'member') |
| `settings` | Global key/value app settings (app_title, default_team_logo) |
| `lists` | Team lists with visibility, type (member/free), date, description |
| `columns` | EAV column definitions (global: list_id IS NULL; local: list_id IS NOT NULL) |
| `list_global_columns` | Which global columns appear in each list |
| `cells` | EAV values — one row per (list, column, player) |
| `files` | Markdown documents (coordinator + member, own table, self-init) |
| `free_list_rows` | Custom rows for free-type lists (self-init) |

Admin credentials live in `config.php` / environment variables — not in the DB.
```
  </action>
  <verify>grep -c "Koordinator" CLAUDE.md && grep -c "not yet established" CLAUDE.md | grep -q "^0$" && grep "src/coordinator/" CLAUDE.md | wc -l</verify>
  <done>CLAUDE.md uses "Koordinator"/"Mitglied" throughout; Conventions section has real patterns; Architecture section has directory layout and DB table list</done>
</task>

<task type="auto">
  <name>Task 2: Update README.md — project structure, login table, DB section</name>
  <files>README.md</files>
  <action>
Update three areas in README.md:

**Area 1 — Login table** (under `### Login`):

Add Koordinator and Mitglied rows. The existing table only shows Admin. Replace:

```markdown
| Rolle | Benutzername | Passwort |
|-------|-------------|---------|
| Admin | `admin` | `admin123` |
```

With:

```markdown
| Rolle | Benutzername | Passwort |
|-------|-------------|---------|
| Admin | `admin` | `admin123` |
| Koordinator | (in Admin-Panel anlegen) | (im Admin-Panel setzen) |
| Mitglied | (vom Koordinator anlegen) | (vom Koordinator setzen) |
```

**Area 2 — Database section** (under `### Datenbankstruktur`):

The current table lists only 4 SQL files and doesn't mention tables added via self-initialization. Update the explanatory note:

After the existing table, add a note:

```markdown
**Hinweis zu selbst-initialisierenden Tabellen:** Die Tabellen `files` und `free_list_rows` werden beim ersten Seitenaufruf automatisch per `IF NOT EXISTS` angelegt (via Self-Init in den Handlern), nicht über `schema.sql`. Dasselbe gilt für zusätzliche Spalten (`list_type`, `brand_color`), die per Migration nachgerüstet wurden.
```

**Area 3 — Projektstruktur** (under `### Projektstruktur`):

Replace the outdated structure block with the actual current layout:

```markdown
### Projektstruktur

```
public/             Webroot (index.php — Front Controller, .htaccess)
src/
  admin/            Admin-Handler (Teams, Koordinatoren, Einstellungen)
  auth/             Login, Logout, Session
  coordinator/      Koordinator-Handler (Listen, Spalten, Mitglieder, Statistik, Dateien, Logo)
  member/           Mitglieder-Handler (Listen, Statistik, Dateien)
  db/               PDO-Verbindung, Sichtbarkeits-Helpers
  templates/
    admin/          Admin-Templates
    coordinator/    Koordinator-Templates
    member/         Mitglieder-Templates
    layout.php      Gemeinsames Login-Layout
    login.php       Login-Seite
  utils/
    csrf.php        CSRF-Token-Generierung und -Validierung
    helpers.php     Hilfsfunktionen (redirect, htmle, require_*)
database/           SQL-Schema und RLS-Richtlinien
docker/             Docker-Konfiguration (nginx, php, postgres)
landing/            Statische Produkt-Landingpage (nicht Teil der App)
uploads/            Logo-Uploads (per .htaccess kein HTTP-Zugriff)
config.php          App-Konfiguration (liest Umgebungsvariablen)
deploy.sh           Hetzner FTP-Deployment-Skript
```
```
  </action>
  <verify>grep -c "Koordinator" README.md && grep "coordinator/" README.md | wc -l && grep "files\|free_list_rows" README.md | wc -l</verify>
  <done>README.md shows Koordinator/Mitglied in login table, has updated project structure with coordinator/ member/ uploads/ landing/ directories, and notes self-initializing tables</done>
</task>

</tasks>

<verification>
After both tasks:
- grep "Trainer\|Spieler\|Coach\|coach" CLAUDE.md (should only appear in stack history/alternatives context, not in project description)
- grep "not yet established\|not yet mapped" CLAUDE.md (should return nothing)
- grep "coordinator/" README.md (should appear in structure section)
- grep "Koordinator" README.md (should appear in login table)
</verification>

<success_criteria>
- CLAUDE.md project description uses correct role names throughout
- CLAUDE.md Conventions section has at least 6 documented conventions (not placeholder text)
- CLAUDE.md Architecture section has directory layout and DB table list
- README.md login table includes Koordinator and Mitglied rows
- README.md project structure matches actual filesystem layout
- README.md notes self-initializing tables (files, free_list_rows)
</success_criteria>

<output>
After completion, create `.planning/quick/260517-fuc-update-all-documentation-to-current-app-/260517-fuc-SUMMARY.md` with what was changed in each file.
</output>
