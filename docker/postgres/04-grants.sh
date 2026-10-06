#!/bin/sh
# Mitgelieferte Datenbank (Profil "db"), erster Start, nach schema.sql (02) und rls_policies.sql (03):
# Rechte für den App-Benutzer — auch auf Tabellen, die später per Migration dazukommen.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
     -v app_user="$DB_USER" <<'EOSQL'
GRANT USAGE ON SCHEMA team_manager TO :"app_user";
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA team_manager TO :"app_user";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA team_manager TO :"app_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA team_manager
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA team_manager
    GRANT USAGE, SELECT ON SEQUENCES TO :"app_user";
EOSQL

echo "[db-init] Rechte für $DB_USER vergeben"
