#!/bin/sh
# Mitgelieferte Datenbank (Profil "db"), erster Start: App-Benutzer anlegen.
# Name und Passwort kommen aus DB_USER/DB_PASS der Env-Datei — dieselben Werte, mit denen
# sich die App verbindet. Die App darf nicht als Superuser verbinden, sonst greift RLS nicht.
set -e

# schema.sql und rls_policies.sql legen alles im Schema team_manager an
if [ "${DB_SCHEMA:-team_manager}" != "team_manager" ]; then
    echo "[db-init] DB_SCHEMA muss für die mitgelieferte Datenbank team_manager sein (ist: $DB_SCHEMA)" >&2
    exit 1
fi
if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    echo "[db-init] DB_USER und DB_PASS fehlen in der Env-Datei" >&2
    exit 1
fi

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
     -v app_user="$DB_USER" -v app_pass="$DB_PASS" <<'EOSQL'
CREATE USER :"app_user" WITH PASSWORD :'app_pass';
EOSQL

echo "[db-init] App-Benutzer angelegt: $DB_USER"
