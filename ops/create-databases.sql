-- Fresh local development server only. Run using the PostgreSQL administrator:
-- psql -U postgres -d postgres -f ops/create-databases.sql
-- Existing roles/databases cause an error; this never resets an installation.
\set ON_ERROR_STOP on
CREATE ROLE memoria_admin LOGIN NOSUPERUSER BYPASSRLS CREATEDB NOCREATEROLE;
\password memoria_admin
CREATE ROLE memoria_app LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
\password memoria_app
CREATE DATABASE memoria OWNER memoria_admin;
CREATE DATABASE memoria_test OWNER memoria_admin;
