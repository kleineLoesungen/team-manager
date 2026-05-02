#!/bin/sh
set -e

# If ADMIN_PASSWORD is set but ADMIN_PASSWORD_HASH is not, generate hash at startup.
# This avoids committing bcrypt hashes to the repo while keeping the dev setup zero-config.
if [ -n "$ADMIN_PASSWORD" ] && [ -z "$ADMIN_PASSWORD_HASH" ]; then
    ADMIN_PASSWORD_HASH=$(php -r "echo password_hash('${ADMIN_PASSWORD}', PASSWORD_BCRYPT, ['cost' => 12]);")
    export ADMIN_PASSWORD_HASH
    echo "[entrypoint] Admin hash generated for user: ${ADMIN_USERNAME:-admin}"
fi

exec "$@"
