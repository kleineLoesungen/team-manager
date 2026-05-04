#!/usr/bin/env bash
set -euo pipefail

FTP_HOST="${1:-}"
FTP_USER="${2:-}"
FTP_PASS="${3:-}"

if [[ -z "$FTP_HOST" || -z "$FTP_USER" || -z "$FTP_PASS" ]]; then
    echo "Usage: ./deploy.sh ftp.your-domain.de username password"
    echo ""
    echo "Uploads to public_html/team-manager/ on the server:"
    echo "  public/index.php + public/.htaccess  → root of webroot"
    echo "  src/ database/                        → alongside index.php"
    echo "  config.php                            → NOT uploaded (create once manually)"
    echo ""
    echo "Requires lftp: brew install lftp (macOS) or apt install lftp (Linux)"
    exit 1
fi

echo "==> Deploying to $FTP_HOST ..."

lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" <<FTPEOF
# Upload src/ and database/ into the webroot
mirror --reverse \
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
    --exclude='public/' \
    . public_html/team-manager/

# Upload public/index.php and public/.htaccess to the webroot root
mirror --reverse \
    public/ public_html/team-manager/

bye
FTPEOF

echo ""
echo "==> Done."
echo ""
echo "First deploy only: create public_html/team-manager/config.php with your DB credentials."
echo "DB tables are created automatically on the first HTTP request."
echo ""
echo "Upgrading existing DB: run the migration scripts once on the server:"
echo "  psql -U <user> -d <database> -f database/migrate_004_rename_roles.sql"
echo "  psql -U <user> -d <database> -f database/migrate_005_rename_member.sql"
echo "Note: The PHP app also runs these migrations automatically on the first request."
