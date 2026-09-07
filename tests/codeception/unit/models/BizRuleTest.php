<?php

namespace tests\codeception\unit\models;

use mdm\admin\models\BizRule;
use tests\codeception\unit\DbTestCase;
use Yii;
use yii\rbac\Rule;

/**
 * Dummy rule that always DENIES. Used as the "old" class of a rule whose
 * className gets switched during an update.
 */
class BizRuleTestDenyRule extends Rule
{
    /** @var int shared property used to prove property-copying on class switch */
    public $level = 1;

    /** @var string property that only exists on the old class */
    public $oldOnly = 'keep-me';

    public function execute($user, $item, $params)
    {
        return false;
    }
}

/**
 * Dummy rule that always ALLOWS. Used as the "new" class after an update.
 */
class BizRuleTestAllowRule extends Rule
{
    /** default differs from BizRuleTestDenyRule::$level so a copied value is detectable */
    public $level = 2;

    public function execute($user, $item, $params)
    {
        return true;
    }
}

/**
 * ABSTRACT dummy rule: class_exists() and is_subclass_of() both pass, but the
 * class can never be instantiated — `new` on it throws an Error (HTTP 500).
 */
abstract class BizRuleTestAbstractRule extends Rule
{
    public function execute($user, $item, $params)
    {
        return true;
    }
}

/**
 * Dummy rule whose constructor REQUIRES an argument: ReflectionClass reports
 * it as instantiable, but `new $class()` (no args) throws an
 * ArgumentCountError, which used to surface as an HTTP 500.
 */
class BizRuleTestCtorRule extends Rule
{
    /** @var string constructor-injected value */
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function execute($user, $item, $params)
    {
        return true;
    }
}

/**
 * BizRule (mdm\admin\models\BizRule) — rule CRUD through the authManager.
 *
 * Regression F11-1: on an EXISTING rule BizRule::save() only pushed the name
 * change through manager->update(); when `className` was changed in the form
 * the loaded instance of the OLD class was serialized back unchanged, so the
 * class switch was silently ineffective (getRule() kept returning the old
 * class). Now an update with a different className builds `new $class()`,
 * copies the still-valid public properties and updates THAT instance, so the
 * new rule class becomes active immediately.
 *
 * Runs on the suite test DB (SQLite by default); the RBAC tables are
 * recreated empty by DbTestCase before every test.
 */
class BizRuleTest extends DbTestCase
{
    public function testCreateStillWorks()
    {
        $model = new BizRule(null);
        $model->name = 'fresh-rule';
        $model->className = BizRuleTestDenyRule::class;
        $this->assertTrue($model->isNewRecord);
        $this->assertTrue($model->save());

        $rule = Yii::$app->authManager->getRule('fresh-rule');
        $this->assertInstanceOf(BizRuleTestDenyRule::class, $rule);
        $this->assertSame('fresh-rule', $rule->name);
    }

    public function testUpdateClassNameSwitchesRuleInstance()
    {
        $auth = Yii::$app->authManager;

        // seed an existing rule of the OLD class with some state
        $old = new BizRuleTestDenyRule();
        $old->name = 'legacy';
        $old->level = 7;
        $auth->add($old);

        // bind the rule to a permission and grant it to user 1
        $perm = $auth->createPermission('updatePost');
        $perm->ruleName = 'legacy';
        $auth->add($perm);
        $auth->assign($perm, '1');

        // old class DENIES => no access while the deny rule is active
        $this->assertFalse($auth->checkAccess(1, 'updatePost'));

        // update the existing BizRule switching className to the ALLOW class
        $model = BizRule::find('legacy');
        $this->assertNotNull($model);
        $this->assertFalse($model->isNewRecord);
        $this->assertSame(BizRuleTestDenyRule::class, $model->className);

        $model->className = BizRuleTestAllowRule::class;
        $this->assertTrue($model->save());

        // getRule() must now return an instance of the NEW class with the
        // still-valid property copied over (name + level) ...
        $rule = $auth->getRule('legacy');
        $this->assertInstanceOf(BizRuleTestAllowRule::class, $rule);
        $this->assertSame('legacy', $rule->name);
        $this->assertSame(7, $rule->level);
        $this->assertFalse(property_exists($rule, 'oldOnly'));

        // ... a fresh BizRule::find() agrees ...
        $reloaded = BizRule::find('legacy');
        $this->assertSame(BizRuleTestAllowRule::class, $reloaded->className);

        // ... and the new class rule is ACTIVE: access is now granted
        $this->assertTrue($auth->checkAccess(1, 'updatePost'));
    }

    public function testUpdateSameClassKeepsInstanceAndRenames()
    {
        $auth = Yii::$app->authManager;

        $rule = new BizRuleTestDenyRule();
        $rule->name = 'legacy';
        $rule->level = 5;
        $auth->add($rule);

        // rename only — class untouched: the loaded instance (state intact)
        // must be reused, not replaced by a fresh unconfigured one
        $model = BizRule::find('legacy');
        $this->assertSame(BizRuleTestDenyRule::class, $model->className);
        $model->name = 'legacy-renamed';
        $this->assertTrue($model->save());

        $renamed = $auth->getRule('legacy-renamed');
        $this->assertInstanceOf(BizRuleTestDenyRule::class, $renamed);
        $this->assertSame('legacy-renamed', $renamed->name);
        $this->assertSame(5, $renamed->level, 'state must survive a plain rename');
        $this->assertNull($auth->getRule('legacy'));
    }

    /**
     * Regression F12-1: an ABSTRACT rule class passes class_exists() but is not
     * instantiable — save() must fail validation with an error on className
     * instead of throwing an Error (HTTP 500) inside `new $class()`.
     */
    public function testSaveAbstractClassFailsValidationWithoutThrow()
    {
        $model = new BizRule(null);
        $model->name = 'abstract-rule';
        $model->className = BizRuleTestAbstractRule::class;

        $this->assertFalse($model->save());
        $this->assertTrue($model->hasErrors('className'));
        $this->assertStringContainsString(
            'instantiable',
            $model->getFirstError('className')
        );
        $this->assertNull(Yii::$app->authManager->getRule('abstract-rule'));
    }

    /**
     * Regression F12-1: a class whose constructor REQUIRES arguments is reported
     * instantiable by ReflectionClass, so validation passes — the
     * ArgumentCountError raised by `new $class()` in save() (create path) must
     * be caught and turned into an error, not a 500.
     */
    public function testCreateWithRequiredCtorArgReturnsFalseInsteadOf500()
    {
        $model = new BizRule(null);
        $model->name = 'ctor-rule';
        $model->className = BizRuleTestCtorRule::class;

        $this->assertFalse($model->save());
        $this->assertTrue($model->hasErrors('className'));
        $this->assertStringContainsString(
            'Failed to instantiate',
            $model->getFirstError('className')
        );
        $this->assertNull(Yii::$app->authManager->getRule('ctor-rule'));
    }

    /**
     * Regression F12-1: same unconstructible-class guard on the update path
     * (className switched to a ctor-required class): save() returns false with
     * an error and the previously stored rule stays untouched.
     */
    public function testUpdateSwitchToUnconstructibleClassReturnsFalseWithoutThrow()
    {
        $auth = Yii::$app->authManager;

        $old = new BizRuleTestDenyRule();
        $old->name = 'legacy-ctor';
        $auth->add($old);

        $model = BizRule::find('legacy-ctor');
        $this->assertNotNull($model);
        $model->className = BizRuleTestCtorRule::class;

        $this->assertFalse($model->save());
        $this->assertTrue($model->hasErrors('className'));
        $this->assertStringContainsString(
            'Failed to instantiate',
            $model->getFirstError('className')
        );

        $rule = $auth->getRule('legacy-ctor');
        $this->assertInstanceOf(BizRuleTestDenyRule::class, $rule);
        $this->assertSame('legacy-ctor', $rule->name);
    }
}
