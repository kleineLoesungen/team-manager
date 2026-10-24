#!/usr/bin/env bash
set -euo pipefail

# Configuration comes from the environment, never from argv — argv is visible to
# any user via `ps` for the lifetime of the process and lands in shell history.
#
#   FTP_HOST   ftp.your-domain.de
#   FTP_USER   ftp username
#   FTP_PASS   ftp password (optional — prompted without echo when unset)
#   FTP_DIR    remote target directory (default: public_html/team-manager)
#   DEPLOY_ENV_FILE  local file uploaded as the server's .env (default: .env.production)
#
# Set them either by exporting, or by creating a .env.deploy file next to this
# script (gitignored, and excluded from the upload below):
#
#   FTP_HOST=ftp.your-domain.de
#   FTP_USER=your-user
#   FTP_DIR=public_html/team-manager
#   # FTP_PASS=...   # optional; omit to be prompted instead
#
# The app's settings (database, admin, mail, cron token …) live in .env.production
# (gitignored, template: .env.production.example). It is uploaded as .env on every
# deploy, so changes there arrive too; config.php reads it on the server.
#
# Requires lftp: brew install lftp (macOS) or apt install lftp (Linux)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ -f "$SCRIPT_DIR/.env.deploy" ]]; then
    set -a
    # shellcheck disable=SC1091
    source "$SCRIPT_DIR/.env.deploy"
    set +a
fi

FTP_HOST="${FTP_HOST:-}"
FTP_USER="${FTP_USER:-}"
FTP_PASS="${FTP_PASS:-}"
FTP_DIR="${FTP_DIR:-public_html/team-manager}"
FTP_DIR="${FTP_DIR%/}/"
DEPLOY_ENV_FILE="${DEPLOY_ENV_FILE:-.env.production}"

if [[ -z "$FTP_HOST" || -z "$FTP_USER" ]]; then
    echo "FTP_HOST and FTP_USER must be set (environment or .env.deploy)." >&2
    echo "" >&2
    echo "  FTP_HOST   ftp.your-domain.de" >&2
    echo "  FTP_USER   ftp username" >&2
    echo "  FTP_PASS   optional — prompted without echo when unset" >&2
    echo "  FTP_DIR    remote target (default: public_html/team-manager)" >&2
    echo "" >&2
    echo "See the comments at the top of this script for a .env.deploy template." >&2
    exit 1
fi

# --- The server's .env (from .env.production) -------------------------------
# config.php has no credentials of its own any more: without this file the site would not
# reach its database. Checked before anything is uploaded.
fehler() { echo "Error: $*" >&2; exit 1; }
cd "$SCRIPT_DIR"
[[ -f "$DEPLOY_ENV_FILE" ]] || fehler "$DEPLOY_ENV_FILE missing — copy .env.production.example to $DEPLOY_ENV_FILE and fill it in (README, Deployment)."
env_value() {   # value of KEY in the env file, without quotes and trailing comment
    local line; line="$(grep -m1 "^$1=" "$DEPLOY_ENV_FILE" || true)"; line="${line#*=}"
    case "$line" in \"*) line="${line#\"}"; line="${line%%\"*}" ;; \'*) line="${line#\'}"; line="${line%%\'*}" ;; *) line="${line%% #*}" ;; esac
    printf '%s' "${line%"${line##*[![:space:]]}"}"
}
for key in DB_HOST DB_NAME DB_SCHEMA DB_USER ADMIN_PASSWORD_HASH; do
    [[ -n "$(env_value "$key")" ]] || fehler "$DEPLOY_ENV_FILE: $key is empty."
done
[[ -z "$(env_value ADMIN_PASSWORD)" ]] \
    || fehler "$DEPLOY_ENV_FILE: ADMIN_PASSWORD would sit on the server in plain text — set ADMIN_PASSWORD_HASH instead (php -r 'echo password_hash(\"…\", PASSWORD_BCRYPT, [\"cost\" => 12]), PHP_EOL;', in single quotes) and leave ADMIN_PASSWORD empty."
# An unquoted " #" starts a comment and cuts the value short without any error on the server.
if grep -nE '^[A-Za-z_][A-Za-z0-9_]*=[^"'\''].* #' "$DEPLOY_ENV_FILE" | grep -v '^[0-9]*:#' >/dev/null; then
    echo "Note: $DEPLOY_ENV_FILE has values with an unquoted ' #' — everything after it is a comment:" >&2
    grep -nE '^[A-Za-z_][A-Za-z0-9_]*=[^"'\''].* #' "$DEPLOY_ENV_FILE" | sed 's/=.*/=…/; s/^/    /' >&2
fi
[[ -n "$(env_value CRON_TOKEN)" ]] || echo "Note: CRON_TOKEN is empty — no deadline reminders without the hourly cron job (README, Cronjob)." >&2

if [[ -z "$FTP_PASS" ]]; then
    read -rsp "FTP password for ${FTP_USER}@${FTP_HOST}: " FTP_PASS
    echo ""
fi
if [[ -z "$FTP_PASS" ]]; then
    echo "No password given — aborting." >&2
    exit 1
fi

# A file that is not world-readable uploads with that mode and the web server then
# returns 403 for it — silently, and only for that one file. A mode-600 app.css once
# served the whole site unstyled while every other asset worked fine.
UNREADABLE="$(find public src database bin -type f ! -perm -o=r 2>/dev/null || true)"
if [[ -n "$UNREADABLE" ]]; then
    echo "==> Fixing files the web server could not read:"
    printf '%s\n' "$UNREADABLE" | sed 's/^/    /'
    # Bump mtime too: lftp mirror compares size and timestamp, so a chmod alone
    # would not cause the file to be re-uploaded.
    printf '%s\n' "$UNREADABLE" | while IFS= read -r f; do chmod a+r "$f"; touch "$f"; done
    echo ""
fi

echo "==> Deploying to ${FTP_HOST}:${FTP_DIR} ..."

# Credentials go over stdin via `open`, not on the lftp command line, so they
# never appear in the process table.
lftp <<FTPEOF
open -u "$FTP_USER,$FTP_PASS" "$FTP_HOST"
# The app's settings first, so the new config.php finds them
put "$DEPLOY_ENV_FILE" -o "${FTP_DIR}.env"
# Upload src/ and database/ into the webroot
mirror --reverse \
    --exclude-glob='.DS_Store' \
    --exclude='.git/' \
    --exclude='docker/' \
    --exclude='docker-compose.yml' \
    --exclude-glob='.env*' \
    --exclude='deploy.sh' \
    --exclude='README.md' \
    --exclude='CLAUDE.md' \
    --exclude='docs/' \
    --exclude='.claude/' \
    --exclude='landing/' \
    --exclude='sessions/' \
    --exclude='^public/' \
    . ${FTP_DIR}

# Upload public/index.php and public/.htaccess to the webroot root
mirror --reverse \
    public/ ${FTP_DIR}

bye
FTPEOF

# --- Database migrations ------------------------------------------------------
# POST /migrate applies missing database/migrations/*.sql (src/migrate.php). The token goes in a
# header read from a private temp file — never on the command line, where `ps` would show it.
MIGRATE_TOKEN_VALUE="$(env_value MIGRATE_TOKEN)"
BASE_URL_VALUE="$(env_value BASE_URL)"
if [[ -n "$MIGRATE_TOKEN_VALUE" && -n "$BASE_URL_VALUE" ]]; then
    echo ""
    echo "==> Applying database migrations ..."
    HEADER_FILE="$(mktemp)"
    chmod 600 "$HEADER_FILE"
    printf 'X-Migrate-Token: %s\n' "$MIGRATE_TOKEN_VALUE" > "$HEADER_FILE"
    URL="${BASE_URL_VALUE%/}"; [[ "$URL" == http* ]] || URL="https://$URL"
    if ! curl -sS --fail-with-body --retry 2 -X POST -H @"$HEADER_FILE" "$URL/migrate"; then
        rm -f "$HEADER_FILE"
        echo "" >&2
        fehler "Migration failed — the new code is online, the database is not up to date. See the answer above and Admin → Einstellungen → Version."
    fi
    rm -f "$HEADER_FILE"
    echo ""
else
    echo ""
    echo "Note: MIGRATE_TOKEN or BASE_URL empty — migrations were not applied (Admin → Einstellungen → Version shows missing ones)." >&2
fi

echo ""
echo "==> Done."
echo ""
echo "Settings came from ${DEPLOY_ENV_FILE} (uploaded as .env)."
echo "First deploy only: the database tables are created on the first HTTP request."
