#!/bin/sh
# Mitgelieferte Datenbank (Profil "db"), erster Start, nach schema.sql (02) und rls_policies.sql (03):
# Rechte für den App-Benutzer aus DB_USER — dieselbe Datei wie bei einer externen DB.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
     -v app_user="$DB_USER" -f /db-init/grants.sql

echo "[db-init] Rechte für $DB_USER vergeben"
