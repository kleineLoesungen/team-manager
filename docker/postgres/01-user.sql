-- Create limited-privilege app user.
-- RLS only filters non-superuser connections — the app must NOT connect as postgres.
CREATE USER team_app WITH PASSWORD 'team_app_dev';
