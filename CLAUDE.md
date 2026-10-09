## Project

**Team Manager**

Eine mobile-first Webanwendung in deutscher Sprache zur Verwaltung von Sportteams. Koordinatoren legen Listen mit frei definierbaren Spalten an, Mitglieder tragen ihre eigenen Daten ein, und eine Statistikseite fasst die Kennzahlen pro Mitglied zusammen. Ein einziger Admin verwaltet Teams und Koordinatoren — alles andere regeln die Koordinatoren selbst.

**Core Value:** Koordinatoren können den Spielereinsatz und beliebige Kennzahlen über alle Listen hinweg erfassen und in einer Statistik pro Mitglied auf einen Blick auswerten.

### Constraints

- **Stack**: PHP + PostgreSQL — kein Framework-Wechsel; JS-Framework nur wenn unvermeidbar
- **Sprache**: Vollständig Deutsch in der UI
- **Mobile-first**: Alle Views primär für Smartphone-Bildschirme gestaltet
- **E-Mail nur für Benachrichtigungen**: von Hand ausgelöst (Koordinator → Mitglieder zu Liste/Dokument, Admin → Koordinatoren), je eine Mail pro Empfänger; kein Login oder Passwort per Mail
- **Einfachheit**: Modernes, schlichtes Design — keine Überladung mit Features

## Technology Stack

- **PHP 8.3+** ohne Framework, PDO mit `pdo_pgsql` (native Prepared Statements, `ATTR_EMULATE_PREPARES=false`)
- **PostgreSQL 14+** mit Row-Level Security
- **Bootstrap 5.3 + Bootstrap Icons per CDN** — kein Node, kein Build-Schritt, kein Sass
- Native PHP-Templates, native Sessions (`httponly`, `samesite=Strict`, `use_strict_mode`)
- `password_hash()` / `password_verify()`, CSRF-Tokens aus `random_bytes()`
- Kein JS-Framework: Formulare funktionieren ohne JavaScript (progressive enhancement)
- Lokale Entwicklung: `docker compose --profile db up` (DB optional, siehe README)

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
- CSRF token on every POST form — `csrf_field()` in the form (field `_csrf`), `require_csrf()` at the top of the POST branch
- `require_coordinator()` / `require_member()` called at top of every protected handler
- Triple-constraint ownership check on row edits: id + team_id + role
- Credentials shown via `credential_modal.php` (full-page include with `Cache-Control: no-store`)

### Templates
- `src/templates/layout.php` — the one layout for all roles (`render_page`), plus login
- `src/templates/admin/layout.php`, `coordinator/layout.php`, `member/layout.php` — thin wrappers
  (`render_admin_page`, `render_coach_page`, `render_member_page`)
- `src/templates/components/partials.php` — shared building blocks: flash, empty state, badges,
  collection groups, danger zone, content rows, tile groups (`render_tile_group`, `render_link_tile`),
  place with maps link (`render_place`), resource picker + live check (`render_resource_picker`),
  series fields (`render_series_fields`), ticker status
- `src/templates/components/event_form.php` — event form for coordinators and members (`$event_role`)
- Bootstrap 5.3 via CDN (no build step)

### UI Patterns

**Verbindlich: `docs/UI-BASELINE.md` vor jeder Frontend-Arbeit lesen.**
Die folgenden Punkte sind die Kurzfassung, nicht der vollständige Vertrag.

- Ein Layout für alle Rollen (`render_page`), ein Stylesheet (`public/css/app.css`).
- Größen nur als Token in `app.css`. In Templates ausschließlich `*-2`, `*-3`, `*-4`.
- Formularfelder nie unter 1rem Schriftgröße (iOS zoomt sonst). Kein `form-control-sm`.
- Alles Antippbare mindestens 44px hoch.
- Sammlungen: gruppierte `list-group` mit Datums-Überschriften. Keine Card-Listen.
- Eine Primäraktion pro Seite, in der klebenden Leiste über der Bottom-Nav.
- Buttons heißen Verb + Objekt („Liste speichern"), nie nur „Speichern".
- Booleans als `form-switch` (bestehend).
- Destruktive Aktionen: Gefahrenzone-Card, dann eigene Bestätigungsseite (bestehend).
- Nach GET-Filtern Scroll-Position wiederherstellen (bestehend).
- Statusfarben: grün läuft/aktiv · grau beendet · gelb wartet · rot inaktiv.
  Immer als `bg-*-subtle`.
- Neues Element gebraucht? Erst in `src/templates/components/` nachsehen.
  Existiert es nicht, dort anlegen — nicht im Seiten-Template.

### Database
- `set_team_context()` called at session start — sets `app.current_role`, `app.current_user_id`, `app.current_team_id` for RLS
- EAV pattern: `columns` table (structure) + `cells` table (values); global columns have `list_id IS NULL`
- Settings stored in `settings` table as key/value pairs (e.g. `app_title`, `default_team_logo`)
- Schema changes: update `database/schema.sql`, `database/rls_policies.sql` and `db_init_schema()`/`db_init_rls()` in `src/db/connection.php` together; ship a one-time script in `database/migrations/` (pure SQL for pgAdmin, `SET LOCAL search_path TO SCHEMA_EINTRAGEN`, `to_regclass` guard, idempotent). The user runs it before deploying; delete it in the next commit once they confirm.
- Cross-team reads (resource usage, a member's teams, other teams' tickers) run briefly in admin context via `as_admin()` (connection.php), which restores the signed-in context; already in admin context it just runs
- Departments: a team only sees resources of its department (`resources_active()` filters by the team); naming follows the domain — `organizations` (not clubs), contents overview `/…/contents` (single lists stay `/…/lists/{id}`)
- Events: members write only their own (`events.created_by`) and only if `teams.members_create_events`; enforced in `src/db/events.php` and by RLS on `events` + `resource_bookings`

### Version / CHANGELOG.md
- The instance version is the top `## YYYY.MM.DD` heading of `CHANGELOG.md`; every instance compares it with `CHANGELOG.md` on GitHub `main` and shows the admin "Update verfügbar" (`src/utils/updates.php`)
- The landing page (`landing/index.html`) loads the same file from GitHub and lists the last 3 versions (without `Migration:` items)
- A new top entry pushed to `main` announces an update to all instances — only add/change CHANGELOG entries after the work is finished and after asking the user; mention migrations as `- Migration: datei.sql`

### Deployment
- `deploy.sh` lftp FTP script for Hetzner Shared Hosting — mirrors repo root + `public/` into `public_html/team-manager/` (no separate apps folder)
- `config.php` never overwritten by deploy (contains production secrets)
- `CHANGELOG.md` is deployed (the instance reads its own version from it)
- `uploads/` directory holds team logos; `.htaccess` blocks direct HTTP access to files

## Architecture

### Directory Layout

```
public/             Webroot — index.php front controller + .htaccess
src/
  admin/            Admin handlers (departments, teams, coordinators, members, organizations, resources, settings)
  auth/             Login, logout, session, role mismatch redirect (role_redirect.php)
  coordinator/      Coordinator handlers (lists, events, columns, members, stats, files, logo, ticker, resources)
  member/           Member handlers (lists, events, stats, files, ticker, resources, coordinators, profile)
  public/           Public (unauthenticated) handlers — ticker overview + detail
  lib/
    phpmailer/      PHPMailer library (bundled, no Composer)
  db/               PDO connection + schema init, domain queries (dashboard, events, resources,
                    visibility, list auto-visibility, ticker, stats, team switch)
  ics_token_handler.php     Team ICS feed (/ics/{token}.ics)
  ics_resource_handler.php  Resource ICS feed (/ics/resource/{token}.ics)
  templates/
    components/     Shared building blocks (partials.php, event_form.php, resource_usage.php)
    admin/          Admin HTML templates
    coordinator/    Coordinator HTML templates
    member/         Member HTML templates
    public/         Public HTML templates (ticker_overview, ticker_detail)
    layout.php      The one layout for all roles (render_page) + login layout
    login.php       Login page
  utils/
    csrf.php        CSRF token generation + validation
    helpers.php     redirect(), htmle(), require_coordinator(), require_member() etc.
database/
  schema.sql        Idempotent schema (all tables)
  rls_policies.sql  Row-Level Security policies
  migrations/       One-time scripts, only until applied (see Database)
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
| `departments` | Departments (e.g. Fußball, Tennis) grouping teams and resources; members and organizations have none |
| `teams` | Teams with name, `department_id`, active flag, logo path, ICS tokens, `members_create_events` |
| `users` | Coordinators and members (role = 'coordinator' or 'member') |
| `coordinator_teams` | Maps coordinators to one or more teams (with left_at for history) |
| `members` | Member profiles (the person, across teams), linked from `users.member_id`; `organization_id` |
| `organizations` | Organizations (e.g. clubs) that members and coordinators belong to — formerly `clubs` |
| `member_attribute_groups` | Groups for custom member attributes (e.g. "Medizin"); `department_id` NULL = all departments, else only that department (coordinators: own team's; members: all of their teams') |
| `member_attributes` | Attribute definitions per group (visible_to_player, editable_by_player) |
| `member_attribute_values` | Attribute values per member |
| `settings` | Global key/value app settings (app_title, default_team_logo) |
| `lists` | Team lists with visibility, type (member/free), date, description |
| `columns` | EAV column definitions (global: list_id IS NULL; local: list_id IS NOT NULL) |
| `list_global_columns` | Which global columns appear in each list |
| `cells` | EAV values — one row per (list, column, player) |
| `files` | Markdown documents per team |
| `events` | Team events (title, date, optional time, place, icon, visibility protected/private, `created_by`) |
| `free_list_rows` | Custom rows for free-type lists |
| `tickers` | Live ticker events per team (status: active/closed, event_date, start_time) |
| `ticker_tags` | Tag labels + color per team for ticker messages |
| `ticker_messages` | Messages posted to a ticker (with optional tag_id) |
| `ticker_members` | Which members have write access to a ticker |
| `ticker_viewers` | Current viewers: hash of session id + ticker id, last heartbeat (rows live minutes, cleared on close) |
| `ticker_viewer_peaks` | Highest concurrent viewer count per ticker, kept until the ticker is deleted |
| `push_subscriptions` | Push-capable devices per signed-in user (endpoint + encryption keys) |
| `ticker_subscriptions` | Opt-in per ticker and user: start notice + every new entry |
| `ticker_push_state` | Marks a ticker's start notice as sent (exactly once) |
| `ticker_seen` | When a user last opened the ticker overview per team (dot on the Ticker tab, "Neu" badge) |
| `resources` | Bookable resources (pitch, hall, bus) of one department, managed by the admin; ics token per resource |
| `resource_bookings` | Which list or event uses a resource (time comes from the list/event; overlaps only warn) |

Admin credentials live in `config.php` / environment variables — not in the DB.
The VAPID key pair for push is generated on first use and stored in `settings` (`vapid_keys`).
