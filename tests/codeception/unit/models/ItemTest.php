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

    public function testTypeNotMassAssignable()
    {
        // POST 'type' must never override the type fixed by the owning
        // controller: even when the payload smuggles Item::TYPE_PERMISSION in
        // 'type', the model stays a role and is persisted as a role.
        $model = new AuthItem();
        $model->type = Item::TYPE_ROLE;
        $loaded = $model->load([
            'AuthItem' => [
                'name' => 'forged-role',
                'description' => 'posted as a role, forged type inside',
                'type' => Item::TYPE_PERMISSION,
                'ruleName' => '',
            ],
        ]);
        $this->assertTrue($loaded);
        $this->assertSame(Item::TYPE_ROLE, $model->type, 'type must not be mass-assignable');
        $this->assertTrue($model->save());
        $this->assertNotNull(Yii::$app->authManager->getRole('forged-role'));
        $this->assertNull(Yii::$app->authManager->getPermission('forged-role'));
    }

    public function testTypeImmutableOnExistingItem()
    {
        $role = new AuthItem();
        $role->name = 'immutable-role';
        $role->type = Item::TYPE_ROLE;
        $this->assertTrue($role->save());

        // an existing item keeps the type it was created with: tampering with
        // it (bypassing mass-assignment) must fail validation and save
        $model = new AuthItem(Yii::$app->authManager->getRole('immutable-role'));
        $model->type = Item::TYPE_PERMISSION;
        $this->assertFalse($model->validate());
        $this->assertArrayHasKey('type', $model->getErrors());
        $this->assertFalse($model->save());
        $this->assertNotNull(Yii::$app->authManager->getRole('immutable-role'));
        $this->assertNull(Yii::$app->authManager->getPermission('immutable-role'));
    }

    public function testRuleNameMustBeRegisteredRule()
    {
        // a class name that is not a registered rule is rejected and must NOT
        // be instantiated/auto-registered as a side effect of validation
        $ruleName = __NAMESPACE__ . '\SampleRule';
        $model = new AuthItem();
        $model->name = 'unruly-role';
        $model->type = Item::TYPE_ROLE;
        $model->ruleName = $ruleName;
        $this->assertFalse($model->validate());
        $this->assertArrayHasKey('ruleName', $model->getErrors());
        $this->assertNull(Yii::$app->authManager->getRule($ruleName));

        // once the same rule is registered through the auth manager (the only
        // legitimate path — RuleController/BizRule), it validates and persists
        $rule = new SampleRule();
        $rule->name = $ruleName;
        Yii::$app->authManager->add($rule);
        $this->assertNotNull(Yii::$app->authManager->getRule($ruleName));

        $model = new AuthItem();
        $model->name = 'ruled-role';
        $model->type = Item::TYPE_ROLE;
        $model->ruleName = $ruleName;
        $this->assertTrue($model->validate());
        $this->assertTrue($model->save());
        $this->assertSame($ruleName, Yii::$app->authManager->getRole('ruled-role')->ruleName);
    }
}

/**
 * Minimal yii\rbac\Rule used to verify AuthItem rule-name handling. It is
 * intentionally NOT registered with any auth manager by default.
 */
class SampleRule extends \yii\rbac\Rule
{
    public function execute($user, $item, $params)
    {
        return true;
    }
}
