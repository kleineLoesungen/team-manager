#!/usr/bin/env bash
set -euo pipefail

FTP_HOST="${1:-}"
FTP_USER="${2:-}"
FTP_PASS="${3:-}"

if [[ -z "$FTP_HOST" || -z "$FTP_USER" || -z "$FTP_PASS" ]]; then
    echo "Usage: ./deploy.sh ftp.your-domain.de username password"
    echo ""
    echo "Uploads everything to public_html/team-manager/ except config.php."
    echo "config.php must be created once manually on the server."
    echo "DB tables are created automatically on the first HTTP request."
    echo ""
    echo "Requires lftp: brew install lftp (macOS) or apt install lftp (Linux)"
    exit 1
fi

echo "==> Deploying to $FTP_HOST ..."

lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" <<FTPEOF
mirror --reverse --delete \
    --exclude-glob='.DS_Store' \
    --exclude='.git/' \
    --exclude='.planning/' \
    --exclude='docker/' \
    --exclude='docker-compose.yml' \
    --exclude='.env' \
    --exclude='.env.docker' \
    --exclude='.env.example' \
    --exclude='config.php' \
    --exclude='deploy.sh' \
    --exclude='README.md' \
    . public_html/team-manager/

bye
FTPEOF

echo ""
echo "==> Done."
echo ""
echo "First deploy only: create public_html/team-manager/config.php on the server with your DB credentials."
echo "DB tables are created automatically on the first HTTP request."
