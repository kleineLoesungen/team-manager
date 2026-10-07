-- database/grants.sql — Rechte für den App-Benutzer im Schema team_manager
--
-- Nach schema.sql und rls_policies.sql ausführen, als Eigentümer der Tabellen.
-- Der Benutzername kommt als psql-Variable:
--   psql -h <host> -U <eigentümer> -d <datenbank> -v app_user=team_app -f database/grants.sql
-- Die mitgelieferte Docker-Datenbank führt das beim ersten Start selbst aus
-- (docker/postgres/04-grants.sh, Benutzer aus DB_USER).
-- Die Default Privileges gelten auch für Tabellen, die später per Migration dazukommen.

GRANT USAGE ON SCHEMA team_manager TO :"app_user";
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA team_manager TO :"app_user";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA team_manager TO :"app_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA team_manager
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA team_manager
    GRANT USAGE, SELECT ON SEQUENCES TO :"app_user";
