#!/usr/bin/env bash
set -euo pipefail

# Configuration comes from the environment, never from argv — argv is visible to
# any user via `ps` for the lifetime of the process and lands in shell history.
#
#   FTP_HOST   ftp.your-domain.de
#   FTP_USER   ftp username
#   FTP_PASS   ftp password (optional — prompted without echo when unset)
#   FTP_DIR    remote target directory (default: public_html/team-manager)
#
# Set them either by exporting, or by creating a .env.deploy file next to this
# script (gitignored, and excluded from the upload below):
#
#   FTP_HOST=ftp.your-domain.de
#   FTP_USER=your-user
#   FTP_DIR=public_html/team-manager
#   # FTP_PASS=...   # optional; omit to be prompted instead
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
# Upload src/ and database/ into the webroot
mirror --reverse \
    --exclude-glob='.DS_Store' \
    --exclude='.git/' \
    --exclude='docker/' \
    --exclude='docker-compose.yml' \
    --exclude-glob='.env*' \
    --exclude='config.php' \
    --exclude='deploy.sh' \
    --exclude='README.md' \
    --exclude='CLAUDE.md' \
    --exclude='docs/' \
    --exclude='.claude/' \
    --exclude='landing/' \
    --exclude='^public/' \
    . ${FTP_DIR}

# Upload public/index.php and public/.htaccess to the webroot root
mirror --reverse \
    public/ ${FTP_DIR}

bye
FTPEOF

echo ""
echo "==> Done."
echo ""
echo "First deploy only: create ${FTP_DIR}config.php with your DB credentials."
echo "DB tables are created automatically on the first HTTP request."
