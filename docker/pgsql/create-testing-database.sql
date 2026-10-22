-- Runs once, when the pgsql volume is first initialised. Creates the database used by PHPUnit.
SELECT 'CREATE DATABASE testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'testing')\gexec
