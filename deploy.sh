#!/usr/bin/env bash
set -euo pipefail

FTP_HOST="${1:-}"
FTP_USER="${2:-}"
FTP_PASS="${3:-}"

if [[ -z "$FTP_HOST" || -z "$FTP_USER" || -z "$FTP_PASS" ]]; then
    echo "Usage: ./deploy.sh ftp.your-domain.de username password"
    echo ""
    echo "Uploads:"
    echo "  public/  → team-manager/"
    echo "  src/ database/ → apps/team-manager/"
    echo ""
    echo "config.php is NOT uploaded — create it once on the server manually."
    echo "DB tables are created automatically on first HTTP request."
    echo ""
    echo "Requires lftp: brew install lftp (macOS) or apt install lftp (Linux)"
    exit 1
fi

echo "==> Deploying to $FTP_HOST ..."

lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" <<FTPEOF
# Webroot: only front controller + .htaccess
mirror --reverse --delete \
    --exclude-glob='.DS_Store' \
    public/ team-manager/

# App source: all files except credentials and dev-only artifacts
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
echo "First deploy only: create apps/team-manager/config.php on the server with DB credentials."
echo "DB tables are created automatically on first HTTP request."
