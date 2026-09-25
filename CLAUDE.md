## Project

**Team Manager**

Eine mobile-first Webanwendung in deutscher Sprache zur Verwaltung von Sportteams. Koordinatoren legen Listen mit frei definierbaren Spalten an, Mitglieder tragen ihre eigenen Daten ein, und eine Statistikseite fasst die Kennzahlen pro Mitglied zusammen. Ein einziger Admin verwaltet Teams und Koordinatoren — alles andere regeln die Koordinatoren selbst.

**Core Value:** Koordinatoren können den Spielereinsatz und beliebige Kennzahlen über alle Listen hinweg erfassen und in einer Statistik pro Mitglied auf einen Blick auswerten.

### Constraints

- **Stack**: PHP + PostgreSQL — kein Framework-Wechsel; JS-Framework nur wenn unvermeidbar
- **Sprache**: Vollständig Deutsch in der UI
- **Mobile-first**: Alle Views primär für Smartphone-Bildschirme gestaltet
- **Keine E-Mail**: Kein SMTP-Setup, kein Mailversand
- **Einfachheit**: Modernes, schlichtes Design — keine Überladung mit Features

## Technology Stack

- **PHP 8.3+** ohne Framework, PDO mit `pdo_pgsql` (native Prepared Statements, `ATTR_EMULATE_PREPARES=false`)
- **PostgreSQL 14+** mit Row-Level Security
- **Bootstrap 5.3 + Bootstrap Icons per CDN** — kein Node, kein Build-Schritt, kein Sass
- Native PHP-Templates, native Sessions (`httponly`, `samesite=Strict`, `use_strict_mode`)
- `password_hash()` / `password_verify()`, CSRF-Tokens aus `random_bytes()`
- Kein JS-Framework: Formulare funktionieren ohne JavaScript (progressive enhancement)
- Lokale Entwicklung: `docker compose up` (siehe README)

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
- No migration files in repo — schema is idempotent via `IF NOT EXISTS`; live DB patched per-task then schema updated

### Deployment
- `deploy.sh` lftp FTP script for Hetzner Shared Hosting — mirrors repo root + `public/` into `public_html/team-manager/` (no separate apps folder)
- `config.php` never overwritten by deploy (contains production secrets)
- `uploads/` directory holds team logos; `.htaccess` blocks direct HTTP access to files

## Architecture

### Directory Layout

```
public/             Webroot — index.php front controller + .htaccess
src/
  admin/            Admin handlers (teams, coordinators, settings, players)
  auth/             Login, logout, session
  coordinator/      Coordinator handlers (lists, columns, members, stats, files, logo, ticker)
  member/           Member handlers (lists, stats, files, ticker, coordinators, profile)
  public/           Public (unauthenticated) handlers — ticker overview + detail
  lib/
    phpmailer/      PHPMailer library (bundled, no Composer)
  db/               PDO connection + visibility helpers
  templates/
    admin/          Admin HTML templates
    coordinator/    Coordinator HTML templates
    member/         Member HTML templates
    public/         Public HTML templates (ticker_overview, ticker_detail)
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
| `coordinator_teams` | Maps coordinators to one or more teams (with left_at for history) |
| `players` | Player profiles linked to users via `player_id` |
| `clubs` | Clubs that players belong to |
| `player_attribute_groups` | Groups for custom player attributes (e.g. "Medizin") |
| `player_attributes` | Attribute definitions per group (visible_to_player, editable_by_player) |
| `player_attribute_values` | Attribute values per player |
| `settings` | Global key/value app settings (app_title, default_team_logo) |
| `lists` | Team lists with visibility, type (member/free), date, description |
| `columns` | EAV column definitions (global: list_id IS NULL; local: list_id IS NOT NULL) |
| `list_global_columns` | Which global columns appear in each list |
| `cells` | EAV values — one row per (list, column, player) |
| `files` | Markdown documents (coordinator + member, own table, self-init) |
| `free_list_rows` | Custom rows for free-type lists (self-init) |
| `tickers` | Live ticker events per team (status: active/closed, event_date, start_time) |
| `ticker_tags` | Tag labels + color per team for ticker messages |
| `ticker_messages` | Messages posted to a ticker (with optional tag_id) |
| `ticker_members` | Which members have write access to a ticker |

Admin credentials live in `config.php` / environment variables — not in the DB.
