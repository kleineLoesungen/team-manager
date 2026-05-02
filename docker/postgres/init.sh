#!/bin/bash
set -e

# Create limited-privilege app user.
# RLS only filters non-superuser connections — the app must connect as team_app, not postgres.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE USER team_app WITH PASSWORD 'team_app_dev';
EOSQL

# Create schema and tables (schema.sql sets search_path to team_manager internally)
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -f /docker-entrypoint-initdb.d/schema.sql

# Apply RLS policies (requires superuser/table owner)
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -f /docker-entrypoint-initdb.d/rls_policies.sql

# Grant team_app access to the schema
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    GRANT USAGE ON SCHEMA team_manager TO team_app;
    GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA team_manager TO team_app;
    GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA team_manager TO team_app;
    ALTER DEFAULT PRIVILEGES IN SCHEMA team_manager
        GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO team_app;
    ALTER DEFAULT PRIVILEGES IN SCHEMA team_manager
        GRANT USAGE, SELECT ON SEQUENCES TO team_app;
EOSQL

echo "Database initialized: schema=team_manager, app_user=team_app"
