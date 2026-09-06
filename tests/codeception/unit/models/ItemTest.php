<?php

namespace tests\codeception\unit\models;

use tests\codeception\unit\DbTestCase;
use mdm\admin\models\AuthItem;
use Yii;
use yii\rbac\Item;

/**
 * AuthItem (mdm\admin\models\AuthItem) — create + uniqueness validation of
 * RBAC items (roles/permissions) against yii\rbac\DbManager.
 *
 * Runs on the suite test DB (SQLite by default, see tests/codeception/config/db.php);
 * the RBAC tables are recreated empty by DbTestCase before every test.
 */
class ItemTest extends DbTestCase
{
    public function testAddNew()
    {
        // missing required attribute 'name' => invalid
        $model = new AuthItem();
        $model->type = Item::TYPE_ROLE;
        $this->assertFalse($model->validate());
        $this->assertArrayHasKey('name', $model->getErrors());
        $this->assertFalse($model->save());

        // valid role => validated, saved and persisted
        $model = new AuthItem();
        $model->name = 'Tester';
        $model->type = Item::TYPE_ROLE;
        $this->assertTrue($model->validate());
        $this->assertTrue($model->save());
        $this->assertNotNull(Yii::$app->authManager->getRole('Tester'));

        // duplicate name => not unique (role namespace)
        $duplicate = new AuthItem();
        $duplicate->name = 'Tester';
        $duplicate->type = Item::TYPE_ROLE;
        $this->assertFalse($duplicate->validate());
        $this->assertArrayHasKey('name', $duplicate->getErrors());

        // the same name as a permission also collides (single name space)
        $permission = new AuthItem();
        $permission->name = 'Tester';
        $permission->type = Item::TYPE_PERMISSION;
        $this->assertFalse($permission->validate());
        $this->assertArrayHasKey('name', $permission->getErrors());

        // a fresh permission name is accepted and persisted
        $permission = new AuthItem();
        $permission->name = 'new-permission';
        $permission->type = Item::TYPE_PERMISSION;
        $this->assertTrue($permission->validate());
        $this->assertTrue($permission->save());
        $this->assertNotNull(Yii::$app->authManager->getPermission('new-permission'));
    }
}
