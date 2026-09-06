<?php
/**
 * Database configuration for the yii2-admin unit tests.
 *
 * The unit suite exercises `yii\rbac\DbManager` for real (AuthItem validation
 * & save, Helper::filter menu checks), so it needs a database — but it must
 * NOT depend on a MySQL/PostgreSQL server being reachable on a fresh checkout:
 *
 *  - Default driver is SQLite, stored as a file under `@runtime`
 *    (`tests/runtime/mdm_admin_test.sqlite`). The RBAC tables are (re)created
 *    by the tests themselves from the official Yii2 schema files
 *    (vendor/yiisoft/yii2/rbac/migrations/schema-<driver>.sql), so plain
 *    `vendor/bin/codecept run -c tests/codeception.yml unit` works with zero
 *    provisioning.
 *
 *  - To run against MySQL or PostgreSQL (local server or CI), set the
 *    environment variable `MDM_ADMIN_TEST_DB` to `mysql` or `pgsql` and make
 *    sure the database exists first — see
 *    `tests/codeception/bin/create-test-db.sh` (credentials are read from the
 *    environment; nothing is hardcoded in this repository). The RBAC tables
 *    are still created automatically by the tests.
 *
 *  - Credentials can be overridden without touching this file by creating a
 *    `db-local.php` next to it (gitignored) which manipulates the `$databases`
 *    and/or `$driver` variables, e.g.:
 *
 *        <?php
 *        $driver = 'mysql';
 *        $databases['mysql']['username'] = 'myname';
 *        $databases['mysql']['password'] = 'changeme';
 *
 *    Never commit real credentials to `db-local.php`.
 */
$databases = [
    'mysql' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=mdm_admin_test',
        'username' => 'travis',
        'password' => '',
    ],
    'sqlite' => [
        'dsn' => 'sqlite:@runtime/mdm_admin_test.sqlite',
    ],
    'pgsql' => [
        'dsn' => 'pgsql:host=localhost;dbname=mdm_admin_test;port=5432;',
        'username' => 'postgres',
        'password' => 'postgres',
    ],
];

// Default to the zero-dependency SQLite driver so that the documented test
// command runs green on a clean checkout without a database server.
$driver = getenv('MDM_ADMIN_TEST_DB') ?: 'sqlite';
if (is_file(__DIR__ . '/db-local.php')) {
    include __DIR__ . '/db-local.php';
}

if (!isset($databases[$driver])) {
    throw new \InvalidArgumentException('Unsupported MDM_ADMIN_TEST_DB driver: ' . $driver
        . ' (supported: ' . implode(', ', array_keys($databases)) . ')');
}

return array_merge(['class' => 'yii\db\Connection'], $databases[$driver]);
