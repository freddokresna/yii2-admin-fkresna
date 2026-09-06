#!/usr/bin/env bash
#
# Create the MySQL/PostgreSQL test database used by the unit suite when it is
# run with MDM_ADMIN_TEST_DB=mysql|pgsql.
#
# The DEFAULT test driver is SQLite (tests/codeception/config/db.php) and needs
# NO database server at all. Use this script only when you deliberately want to
# run the suite against MySQL/PostgreSQL (e.g. CI or a local server).
#
# The RBAC tables (auth_item, auth_item_child, auth_assignment, auth_rule) are
# created/recreated automatically by the tests themselves, so this script only
# has to create the empty database and grant access to the DB user that the
# test connection uses.
#
# Credentials are read from the environment — never hardcode secrets in this
# repository. The application user can also be overridden in the gitignored
# tests/codeception/config/db-local.php (see db.php).
#
# Environment variables:
#   DB_ROOT_USER / DB_ROOT_PASS   admin login (default: root / empty)
#   DB_ROOT_PORT                  admin port (default: 3306 mysql, 5432 pgsql)
#   TEST_DB_NAME                  database name (default: mdm_admin_test)
#   TEST_DB_HOST                  host (default: 127.0.0.1)
#   TEST_DB_PORT                  port (default: 3306 mysql, 5432 pgsql)
#   TEST_DB_USER                  app user (default: travis, matches db.php)
#   TEST_DB_PASS                  app password (default: empty, matches db.php)
#
# Examples:
#   # MySQL, root password given via env (never on the command line):
#   DB_ROOT_PASS='secret' ./create-test-db.sh mysql
#   # PostgreSQL with explicit app credentials:
#   DB_ROOT_PASS='secret' TEST_DB_USER=mdm TEST_DB_PASS='app-secret' ./create-test-db.sh pgsql
#
set -euo pipefail

DRIVER="${1:-mysql}"
DB_NAME="${TEST_DB_NAME:-mdm_admin_test}"
DB_HOST="${TEST_DB_HOST:-127.0.0.1}"
DB_USER="${TEST_DB_USER:-travis}"
DB_PASS="${TEST_DB_PASS:-}"

case "$DRIVER" in
  mysql)
    DB_PORT="${TEST_DB_PORT:-3306}"
    MYSQL_PWD="${DB_ROOT_PASS:-}" mysql -h "$DB_HOST" -P "${DB_ROOT_PORT:-$DB_PORT}" -u "${DB_ROOT_USER:-root}" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'$DB_HOST' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'$DB_HOST';
FLUSH PRIVILEGES;
SQL
    ;;
  pgsql)
    DB_PORT="${TEST_DB_PORT:-5432}"
    PGPASSWORD="${DB_ROOT_PASS:-}" psql -h "$DB_HOST" -p "${DB_ROOT_PORT:-$DB_PORT}" -U "${DB_ROOT_USER:-postgres}" -v ON_ERROR_STOP=1 <<SQL
SELECT 'CREATE DATABASE $DB_NAME' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '$DB_NAME')\gexec
SQL
    PGPASSWORD="${DB_ROOT_PASS:-}" psql -h "$DB_HOST" -p "${DB_ROOT_PORT:-$DB_PORT}" -U "${DB_ROOT_USER:-postgres}" -d "$DB_NAME" -v ON_ERROR_STOP=1 <<SQL
DO \$\$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '$DB_USER') THEN
      EXECUTE format('CREATE ROLE %I LOGIN PASSWORD %L', '$DB_USER', '$DB_PASS');
   END IF;
END
\$\$;
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
GRANT ALL ON SCHEMA public TO $DB_USER;
SQL
    ;;
  *)
    echo "usage: $0 [mysql|pgsql]   (default driver of the suite is sqlite and needs no script)" >&2
    exit 2
    ;;
esac

echo "[create-test-db] database '$DB_NAME' ready on $DRIVER ($DB_HOST); test user: '$DB_USER'"
echo "[create-test-db] run the suite with: MDM_ADMIN_TEST_DB=$DRIVER vendor/bin/codecept run -c tests/codeception.yml unit"
