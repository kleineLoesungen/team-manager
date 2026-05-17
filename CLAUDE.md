<!-- GSD:project-start source:PROJECT.md -->
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
<!-- GSD:project-end -->

<!-- GSD:stack-start source:research/STACK.md -->
## Technology Stack

## Recommended Stack
### Core Runtime & Language
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| PHP | 8.3+ (8.4+ preferred) | Server-side application logic | Current stable version with security patches; 8.4 adds improved JSON support and performance |
| PostgreSQL | 14+ (15+ preferred) | Relational database | ACID compliance, excellent JSON support, superior to MySQL for complex queries and team data |
### Web Server
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| nginx or Apache 2.4+ | Latest stable | HTTP server | nginx is lighter and faster for PHP-FPM; Apache is simpler for shared hosting. Either works for team manager scale. |
| PHP-FPM | Bundled with PHP | FastCGI process manager | Standard approach for separating web server from PHP runtime; essential for performance and security |
### Database Access (Abstraction)
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| PDO (PHP Data Objects) | Built-in since PHP 5.1 | Database access layer | Ships with PHP; provides consistent interface across databases; supports prepared statements (SQL injection prevention); lighter weight than ORMs |
| PDO_PGSQL driver | Built-in with PHP | PostgreSQL-specific driver | Official driver for PostgreSQL; required to use PDO with Postgres |
### Authentication & Security
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| password_hash() / password_verify() | Built-in (PHP 5.5+) | Password hashing | Official PHP recommendation; uses bcrypt (PASSWORD_DEFAULT) with automatic salt generation; configurable cost parameter |
| session_start() with options | Built-in (PHP 7.1+ with enhancements in 8.x) | Session management | Native PHP sessions with security best practices: `cookie_secure`, `cookie_httponly`, `cookie_samesite`, `use_strict_mode` |
| CSRF token generation | Built-in via $_SERVER superglobal | CSRF protection | Use random tokens from `random_bytes()` and validate via `hash()` or `bin2hex()` |
### Input Validation & Sanitization
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| filter_var() / filter_input() | Built-in (PHP 5.2+) | Input validation & sanitization | Official approach for validating emails, URLs, integers; prevents invalid data in database |
| htmlspecialchars() | Built-in | Output escaping | Prevents XSS attacks; required when outputting user data to HTML |
### Frontend CSS Framework
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Bootstrap | 5.3+ (via CDN) | Mobile-first CSS framework | Pre-built components (forms, tables, cards); mobile-first design; no build step required via CDN; excellent documentation; widespread browser support |
| Pure CSS with Flexbox/Grid | CSS3 (native browser support) | Layout & responsive design | Modern CSS handles mobile-first without framework; use `@media` queries for breakpoints; Flexbox for forms and lists; Grid for complex layouts |
### Form & Template Handling
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Native PHP templating | N/A | HTML output & form rendering | For small, simple projects, native PHP (heredoc, nowdoc, interpolation) is sufficient; no dependency overhead; direct control over HTML output |
| No separate template engine required | N/A | Keep build complexity low | Twig/Blade add unnecessary complexity; this project has predictable, German-language UI without dynamic template inheritance needs |
### JSON Handling
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| json_encode() / json_decode() | Built-in (PHP 5.2+) | JSON serialization | For API responses, configuration, data export; built-in with excellent performance |
### Development Tools
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Composer | 2.5+ (optional) | PHP package manager | Only if external dependencies needed; for this stack, all core features use built-in PHP |
| Git | 2.30+ | Version control | Standard for all projects |
## Installation
### Initial Setup
# PostgreSQL (macOS/Homebrew example)
# PHP with required extensions (macOS/Homebrew)
# Enable PHP-FPM
# Verify installations
# Create .env or config file
### Database Initialization
### Session Configuration (Secure)
### HTML Template Boilerplate
### Form Handling with CSRF Protection
### Password Management
## Alternatives Considered
| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Framework | None (vanilla PHP) | Laravel, Symfony | Adds complexity for simple CRUD app; slows onboarding; overkill for single admin, fixed features |
| Database abstraction | PDO | Doctrine ORM, Eloquent | ORMs add overhead; PDO prepared statements sufficient for straightforward queries; simpler to debug |
| CSS framework | Bootstrap (CDN) | Tailwind CSS | Tailwind requires Node.js build pipeline; contradicts "keep it simple" requirement and no heavy JS framework constraint |
| Frontend JS | None (progressive enhancement) | React, Vue, Alpine | Not needed for team manager workflow; HTMX/htmx could add interactivity without framework overhead if required later |
| Session storage | Native PHP $_SESSION | Redis, Memcached | PHP file-based sessions sufficient for single-admin, small team scale; Redis adds infrastructure burden |
| Template engine | Native PHP | Twig, Blade | Native PHP is simpler and faster for straightforward HTML; no inheritance complexity needed |
| Password hashing | password_hash() | bcrypt directly, custom | Official PHP recommendation; PASSWORD_DEFAULT evolves as algorithms improve |
## Stack Rationale Summary
- **PHP 8.3+**: Modern language features, built-in security, stable, widely hosted
- **PostgreSQL**: Superior JSON/JSONB support (for flexible column types), ACID guarantee, better for team data integrity
- **PDO**: Lightweight, prevents SQL injection via prepared statements, no ORM overhead
- **Native Sessions**: Built-in, secure with proper configuration, no external dependencies
- **Bootstrap via CDN**: Mobile-first, no build step, immediate styling, extensive docs
- **No framework**: All business logic in simple PHP functions/classes; clear request → process → respond flow
- No Node.js build pipeline → No npm, no webpack, no asset compilation
- No ORM → Direct SQL queries with PDO parameterization; easier to understand and optimize
- No JS framework → HTML/CSS/minimal JS; forms work without JavaScript; progressive enhancement
## Confidence Assessment
| Area | Confidence | Notes |
|------|------------|-------|
| PHP version (8.3+) | HIGH | Official docs confirm; security updates current; widely deployed |
| PDO for database access | HIGH | Official PHP recommendation; prepares statements natively; PostgreSQL driver built-in |
| password_hash() / session_start() | HIGH | Built-in, documented, widely tested; PASSWORD_DEFAULT evolves safely |
| Bootstrap 5 via CDN | HIGH | Stable, mobile-first, widely supported; no build dependency |
| No framework | HIGH | Verified against project constraints: simple CRUD, fixed UI, no heavy JS |
| Native sessions configuration | MEDIUM-HIGH | Security options verified; requires careful configuration but no external service needed |
| PostgreSQL 14+ | HIGH | Stable, excellent team data support, ACID, JSON columns for future flexibility |
## Next Steps for Phase 1
## Sources
- [PHP Documentation](https://www.php.net/manual/en/) - Official PHP reference, password hashing, PDO, sessions, filtering
- [PostgreSQL Official Docs](https://www.postgresql.org/docs/) - Database documentation
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.3/) - Mobile-first CSS framework
- [MDN Web Docs - Responsive Design](https://developer.mozilla.org/en-US/docs/Learn/CSS/) - CSS best practices, mobile-first approach
- [OWASP PHP Security](https://owasp.org/www-community/) - Security best practices for session, CSRF, input validation
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
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
- `deploy.sh` lftp FTP script for Hetzner Shared Hosting — mirrors repo root + `public/` into `public_html/team-manager/` (no separate apps folder)
- `config.php` never overwritten by deploy (contains production secrets)
- `uploads/` directory holds team logos; `.htaccess` blocks direct HTTP access to files
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
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
<!-- GSD:architecture-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd:quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd:debug` for investigation and bug fixing
- `/gsd:execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->



<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd:profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
