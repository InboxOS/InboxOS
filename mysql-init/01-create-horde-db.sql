-- Initialize Horde database on first MySQL startup.
-- NOTE: This runs only when /var/lib/mysql is empty.

CREATE DATABASE IF NOT EXISTS horde
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- NOTE:
-- We create the Horde DB user/grants in deploy.sh so credentials can come from `.env`
-- (MySQL init scripts are not environment-variable templated).

