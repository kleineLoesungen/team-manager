---
id: 260501-ahg
description: "restructure for Hetzner shared hosting: public_html/team-manager webroot, app source outside webroot, FTP deploy, DB self-init"
mode: quick
---

# Quick Plan 260501-ahg: Hetzner Shared Hosting

## Goal

Deployment = FTP upload only. No manual DB setup ever. Server structure:
- `~/public_html/team-manager/` — webroot (index.php + .htaccess only)
- `~/apps/team-manager/` — source files: src/, database/, config.php (physically outside webroot, never HTTP-accessible)
- `config.php` is excluded from FTP upload so server credentials persist across deploys

## Server Structure

```
~/                              (Hetzner FTP home)
├── public_html/
│   └── team-manager/           ← subdomain document root
│       ├── index.php
│       └── .htaccess
└── apps/
    └── team-manager/           ← source (NOT in webroot)
        ├── config.php          ← credentials, safe without .htaccess
        ├── src/
        └── database/
```

## Tasks

### Task 1: ROOT_PATH auto-detection in public/index.php

**Files:**
- `public/index.php`

**Action:**

Replace the hardcoded `define('ROOT_PATH', dirname(__DIR__));` with auto-detection:

```php
// Dev: src/ is sibling of public/ → ROOT_PATH = parent of public/
// Hetzner: deployed to public_html/team-manager/; source is at ~/apps/team-manager/
$_parent = dirname(__DIR__);
if (is_dir($_parent . '/src')) {
    define('ROOT_PATH', $_parent); // development
} else {
    define('ROOT_PATH', dirname($_parent) . '/apps/team-manager'); // Hetzner production
}
unset($_parent);
```

Logic:
- `dirname(__DIR__)` from `public/index.php` in dev = project root (has src/ as sibling) ✓
- `dirname(__DIR__)` from `public_html/team-manager/index.php` on Hetzner = `~/public_html` (no src/ there)
- `dirname(dirname(__DIR__))` = `~/`, then + `/apps/team-manager` = correct source path ✓

**Verify:** `public/index.php` has conditional ROOT_PATH definition.

**Done:** `feat(hetzner): add ROOT_PATH auto-detection for Hetzner vs dev`

---

### Task 2: DB self-init in src/db/connection.php

**Files:**
- `src/db/connection.php`

**Action:**

Add `maybe_init_db(PDO $pdo): void` function after `get_db()`. Call it inside `get_db()` after the PDO instance is created (before returning $pdo).

```php
function maybe_init_db(PDO $pdo): void {
    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', DB_SCHEMA);
    // Check if schema/tables already exist
    $exists = $pdo->query("SELECT to_regclass('{$schema}.teams')")->fetchColumn();
    if ($exists !== null) return;

    // First boot: initialize schema and RLS policies
    $pdo->exec(file_get_contents(ROOT_PATH . '/database/schema.sql'));
    $pdo->exec(file_get_contents(ROOT_PATH . '/database/rls_policies.sql'));
}
```

Call it in `get_db()` after `$pdo->exec("SET search_path TO ...")`:
```php
    maybe_init_db($pdo);
    return $pdo;
```

Note: Uses the single Hetzner DB user for both init and app queries — no separate admin user needed. On first HTTP request when the DB is empty, tables are created automatically.

**Verify:** `maybe_init_db()` exists, is called in `get_db()`, checks `to_regclass` before running SQL.

**Done:** `feat(hetzner): DB self-init on first request`

---

### Task 3: FORCE ROW LEVEL SECURITY in rls_policies.sql

**Files:**
- `database/rls_policies.sql`

**Action:**

On Hetzner, the single DB user is the table owner. PostgreSQL table owners bypass RLS by default. Add `FORCE ROW LEVEL SECURITY` after each `ENABLE ROW LEVEL SECURITY` so policies apply even to the owner.

After `ALTER TABLE users ENABLE ROW LEVEL SECURITY;` add:
```sql
ALTER TABLE users FORCE ROW LEVEL SECURITY;
```

Find all `ENABLE ROW LEVEL SECURITY` lines in the file and add a matching `FORCE` line after each one (users, lists, columns, cells tables).

**Verify:** Each table with `ENABLE ROW LEVEL SECURITY` has a corresponding `FORCE ROW LEVEL SECURITY`.

**Done:** `fix(rls): force RLS for table owner (Hetzner single-user setup)`

---

### Task 4: FTP deploy script + README update

**Files:**
- `deploy.sh` — rewrite for FTP with lftp
- `README.md` — update Deployment section

**Action:**

Rewrite `deploy.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

FTP_HOST="${1:-}"
FTP_USER="${2:-}"
FTP_PASS="${3:-}"

if [[ -z "$FTP_HOST" || -z "$FTP_USER" || -z "$FTP_PASS" ]]; then
    echo "Usage: ./deploy.sh ftp.your-domain.de username password"
    echo ""
    echo "Uploads:"
    echo "  public/  → public_html/team-manager/"
    echo "  src/ database/ config.php (except if exists on server) → apps/team-manager/"
    echo ""
    echo "Requires lftp: brew install lftp (macOS) or apt install lftp (Linux)"
    exit 1
fi

echo "==> Deploying to $FTP_HOST ..."

lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" <<FTPEOF
# Webroot: only the front controller and .htaccess
mirror --reverse --delete \
    --exclude-glob='*.DS_Store' \
    public/ public_html/team-manager/

# App source: everything except credentials and dev artifacts
mirror --reverse \
    --exclude='.git/' \
    --exclude='.planning/' \
    --exclude='docker/' \
    --exclude='docker-compose.yml' \
    --exclude='.env' \
    --exclude='.env.docker' \
    --exclude='.env.example' \
    --exclude='config.php' \
    --exclude='deploy.sh' \
    --exclude='public/' \
    --exclude='README.md' \
    . apps/team-manager/

bye
FTPEOF

echo ""
echo "==> Done."
echo ""
echo "First deploy only:"
echo "  1. FTP: copy config.php to apps/team-manager/config.php and fill in credentials"
echo "  2. DB and tables are created automatically on first HTTP request"
```

Note: `config.php` is excluded from the mirror so server credentials are never overwritten. `--delete` is NOT used for the source mirror to avoid deleting config.php on the server.

Update `README.md` deployment section: replace the 5-step "Deployment auf bestehendem Webserver" with the new Hetzner structure, lftp command, and one-time config.php setup step.

**Verify:** `deploy.sh` uses lftp, uploads `public/` to `public_html/team-manager/`, uploads source to `apps/team-manager/`, excludes `config.php`.

**Done:** `feat(hetzner): FTP deploy script and updated README`
