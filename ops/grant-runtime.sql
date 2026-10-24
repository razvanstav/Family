-- Run as memoria_admin after migrations, for both memoria and memoria_test.
\set ON_ERROR_STOP on
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO memoria_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO memoria_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO memoria_app;
REVOKE ALL ON migrations FROM memoria_app;
REVOKE INSERT, UPDATE ON users FROM memoria_app;
GRANT INSERT (name, email, email_verified_at, password, remember_token, created_at, updated_at) ON users TO memoria_app;
GRANT UPDATE (name, email, email_verified_at, password, remember_token, created_at, updated_at) ON users TO memoria_app;
