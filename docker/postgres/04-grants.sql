-- Grant team_app access to all objects in the team_manager schema.
-- Run after schema.sql (02) and rls_policies.sql (03).
GRANT USAGE ON SCHEMA team_manager TO team_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA team_manager TO team_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA team_manager TO team_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA team_manager
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO team_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA team_manager
    GRANT USAGE, SELECT ON SEQUENCES TO team_app;
