#!/usr/bin/env bash
set -euo pipefail

DEST="${1:-}"

if [[ -z "$DEST" ]]; then
    echo "Usage: ./deploy.sh user@server:/var/www/team-manager"
    exit 1
fi

echo "Deploying to $DEST ..."

rsync -avz --delete \
    --exclude='.git/' \
    --exclude='.planning/' \
    --exclude='docker/' \
    --exclude='docker-compose.yml' \
    --exclude='.env' \
    --exclude='.env.docker' \
    --exclude='.env.example' \
    --exclude='deploy.sh' \
    . "$DEST"

echo ""
echo "Done. Don't forget:"
echo "  1. Set environment variables on the server (see README.md)"
echo "  2. Point webroot to public/"
echo "  3. On first deploy: run database/schema.sql and database/rls_policies.sql"
