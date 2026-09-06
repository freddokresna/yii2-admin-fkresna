<?php

namespace tests\codeception\unit;

use Yii;

/**
 * TestCase for tests that need a real RBAC database (yii\rbac\DbManager).
 *
 * The unit suite defaults to the SQLite driver (file `@runtime/mdm_admin_test.sqlite`,
 * see tests/codeception/config/db.php), so no external database server is
 * needed. Before every test the RBAC tables (`auth_rule`, `auth_item`,
 * `auth_item_child`, `auth_assignment`) are (re)created from the official Yii2
 * schema files (vendor/yiisoft/yii2/rbac/migrations/schema-<driver>.sql), so
 * each test starts from a deterministic, empty database.
 *
 * When `MDM_ADMIN_TEST_DB=mysql`/`pgsql` is set the same schema file for that
 * driver is applied instead; only the (empty) database itself has to exist
 * first — see tests/codeception/bin/create-test-db.sh.
 */
abstract class DbTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetRbacSchema();
    }

    /**
     * (Re)create the RBAC tables on the currently configured test DB driver.
     */
    protected function resetRbacSchema()
    {
        $db = Yii::$app->db;
        $driver = $db->driverName;
        $schemaFile = Yii::getAlias('@vendor/yiisoft/yii2/rbac/migrations/schema-' . $driver . '.sql');
        if (!is_file($schemaFile)) {
            $this->markTestSkipped('No yii\rbac\DbManager schema file for DB driver "' . $driver
                . '" (' . $schemaFile . '); cannot provision the RBAC tables.');
        }

        $sql = file_get_contents($schemaFile);
        // strip SQL comments, then execute statement by statement
        $sql = preg_replace('~/\*.*?\*/~s', '', $sql);
        $sql = preg_replace('~^--.*$~m', '', $sql);
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                $db->createCommand($statement)->execute();
            }
        }

        if (Yii::$app->authManager instanceof \yii\rbac\DbManager) {
            Yii::$app->authManager->invalidateCache();
        }
        \mdm\admin\components\Helper::invalidate();
    }
}
