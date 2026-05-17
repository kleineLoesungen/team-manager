# Team Manager

Mobile-first Webanwendung zur Verwaltung von Sportteams. Koordinatoren legen Listen mit frei definierbaren Spalten an, Mitglieder tragen ihre eigenen Daten ein, und eine Statistikseite fasst die Kennzahlen pro Mitglied zusammen.

**Stack:** PHP 8.3 · PostgreSQL 15 · Bootstrap 5 · kein Framework

---

## Dev-Umgebung (Docker)

### Voraussetzungen

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) ≥ 4.x

### Starten

```bash
docker compose up --build
```

Die App ist danach unter **http://localhost:8080** erreichbar.

Beim ersten Start:
- PostgreSQL initialisiert die Datenbank und legt das Schema `team_manager` an
- Der App-Benutzer `team_app` wird mit den nötigen Berechtigungen angelegt
- Der Admin-Passwort-Hash wird automatisch aus `ADMIN_PASSWORD` generiert

### Login

| Rolle | Benutzername | Passwort |
|-------|-------------|---------|
| Admin | `admin` | `admin123` |
| Koordinator | (im Admin-Panel anlegen) | (im Admin-Panel setzen) |
| Mitglied | (vom Koordinator anlegen) | (vom Koordinator setzen) |

Admin-Zugangsdaten werden in [.env.docker](.env.docker) konfiguriert.

### Services

| Service | Image | Port |
|---------|-------|------|
| nginx | nginx:1.25-alpine | 8080 → 80 |
| php | php:8.3-fpm-alpine | intern (9000) |
| db | postgres:15-alpine | intern (5432) |

### Datenbank zurücksetzen

```bash
docker compose down -v && docker compose up
```

`-v` löscht das Postgres-Volume — die Initialisierungsskripte laufen beim nächsten Start neu durch.

### Konfiguration

Alle Umgebungsvariablen für die Dev-Umgebung stehen in [.env.docker](.env.docker):

| Variable | Beschreibung | Standard |
|----------|-------------|---------|
| `DB_NAME` | Datenbankname | `team_manager_db` |
| `DB_SCHEMA` | PostgreSQL-Schema | `team_manager` |
| `DB_USER` | App-Datenbankbenutzer | `team_app` |
| `DB_PASS` | Passwort des App-Benutzers | `team_app_dev` |
| `ADMIN_USERNAME` | Admin-Benutzername | `admin` |
| `ADMIN_PASSWORD` | Klartextpasswort (wird beim Start gehasht) | `admin123` |
| `APP_ENV` | Umgebung | `development` |

### Datenbankstruktur

Die SQL-Dateien unter `database/` werden beim ersten Start in dieser Reihenfolge ausgeführt:

| Datei | Inhalt |
|-------|--------|
| `docker/postgres/01-user.sql` | Legt den App-Benutzer `team_app` an |
| `database/schema.sql` | Erstellt Schema `team_manager` und alle Tabellen |
| `database/rls_policies.sql` | Aktiviert Row-Level Security auf `users` |
| `docker/postgres/04-grants.sql` | Erteilt `team_app` die nötigen Rechte |

**Hinweis zur Row-Level Security:** Die App verbindet sich als `team_app` (kein Superuser), damit RLS greift. Admin-Requests setzen `app.is_admin = true`, Koordinator/Mitglied-Requests setzen `app.current_team_id`.

**Hinweis zu selbst-initialisierenden Tabellen:** Die Tabellen `files` und `free_list_rows` werden beim ersten Seitenaufruf automatisch per `IF NOT EXISTS` angelegt (via Self-Init in den Handlern), nicht über `schema.sql`. Dasselbe gilt für zusätzliche Spalten (`list_type`, `brand_color`), die per Migration nachgerüstet wurden.

---

## Deployment (Hetzner Webhosting)

### Serverstruktur

```
~/                              (FTP-Home)
├── public_html/
│   └── team-manager/           ← Subdomain-Webroot
│       ├── index.php
│       └── .htaccess
└── apps/
    └── team-manager/           ← Quelldateien (kein HTTP-Zugriff möglich)
        ├── config.php          ← Zugangsdaten (einmalig anlegen, nie überschreiben)
        ├── src/
        └── database/
```

### Ersteinrichtung

**1. Subdomain anlegen** (Hetzner Konsole) mit Webroot `public_html/team-manager/`.

**2. config.php anlegen** — per FTP nach `apps/team-manager/config.php` hochladen und Zugangsdaten eintragen:

```php
<?php
define('ADMIN_USERNAME',     'admin');
define('ADMIN_PASSWORD_HASH', password_hash('IhrPasswort', PASSWORD_BCRYPT, ['cost' => 12]));

define('DB_HOST',   'localhost');
define('DB_PORT',   '5432');
define('DB_NAME',   'ihre_datenbank');
define('DB_SCHEMA', 'team_manager');
define('DB_USER',   'ihr_db_benutzer');
define('DB_PASS',   'ihr_db_passwort');

define('SESSION_TIMEOUT', 8 * 60 * 60);
define('APP_ENV', 'production');
define('BASE_URL', '');
```

**3. Dateien hochladen:**

```bash
./deploy.sh ftp.ihre-domain.de benutzername passwort
```

Erfordert `lftp`: `brew install lftp` (macOS) oder `apt install lftp` (Linux).

**4. Erste Anfrage** — beim ersten Seitenaufruf werden Datenbanktabellen automatisch angelegt.

### Folge-Deployments

```bash
./deploy.sh ftp.ihre-domain.de benutzername passwort
```

`config.php` wird nie überschrieben. Datenbanktabellen werden nicht erneut angelegt (idempotente Prüfung).

---

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
