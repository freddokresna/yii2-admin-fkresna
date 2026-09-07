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
 * Regression F13-1: creating a rule with a name that already exists — or
 * renaming an existing rule onto another rule's name — used to reach
 * DbManager::add()/update() and throw an IntegrityException (HTTP 500) on the
 * UNIQUE(auth_rule.name) constraint. checkUniqueName() (mirroring
 * AuthItem::checkUnique) now rejects the duplicate during validation, so
 * save() returns false with an error on 'name' and nothing is written.
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

    /**
     * Regression F13-1: creating a rule whose name already exists must fail
     * validation with an error on 'name' — not throw an IntegrityException
     * (HTTP 500) from DbManager::add() on the UNIQUE(auth_rule.name)
     * constraint.
     */
    public function testCreateDuplicateNameFailsValidationWithoutThrow()
    {
        $auth = Yii::$app->authManager;

        $existing = new BizRuleTestDenyRule();
        $existing->name = 'taken';
        $auth->add($existing);

        $model = new BizRule(null);
        $model->name = 'taken';
        $model->className = BizRuleTestDenyRule::class;

        $this->assertFalse($model->save());
        $this->assertTrue($model->hasErrors('name'));
        $this->assertStringContainsString(
            'has already been taken',
            $model->getFirstError('name')
        );

        // original rule untouched, no second rule with the same name appears
        $rule = $auth->getRule('taken');
        $this->assertInstanceOf(BizRuleTestDenyRule::class, $rule);
        $this->assertNull($auth->getRule('taken-dup'));
    }

    /**
     * Regression F13-1: renaming an existing rule onto the name of another
     * registered rule must fail validation with an error on 'name' instead of
     * throwing an IntegrityException (HTTP 500) from DbManager::update().
     * Both original rules stay intact (no partial rename).
     */
    public function testRenameToExistingRuleNameFailsValidationWithoutThrow()
    {
        $auth = Yii::$app->authManager;

        $keep = new BizRuleTestDenyRule();
        $keep->name = 'keep';
        $keep->level = 3;
        $auth->add($keep);

        $target = new BizRuleTestDenyRule();
        $target->name = 'target';
        $target->level = 9;
        $auth->add($target);

        // rename 'keep' onto the taken name 'target'
        $model = BizRule::find('keep');
        $this->assertNotNull($model);
        $model->name = 'target';

        $this->assertFalse($model->save());
        $this->assertTrue($model->hasErrors('name'));
        $this->assertStringContainsString(
            'has already been taken',
            $model->getFirstError('name')
        );

        // nothing changed: 'keep' still exists under its own name (state
        // intact), 'target' still exists and was not overwritten
        $stillKeep = $auth->getRule('keep');
        $this->assertInstanceOf(BizRuleTestDenyRule::class, $stillKeep);
        $this->assertSame(3, $stillKeep->level);

        $stillTarget = $auth->getRule('target');
        $this->assertInstanceOf(BizRuleTestDenyRule::class, $stillTarget);
        $this->assertSame(9, $stillTarget->level);
    }

    /**
     * Regression F13-1: an update that does not change the name (same-name
     * save) must not trip the uniqueness check — the when-clause skips it.
     */
    public function testUpdateKeepingOwnNamePassesUniquenessCheck()
    {
        $auth = Yii::$app->authManager;

        $rule = new BizRuleTestDenyRule();
        $rule->name = 'own-name';
        $auth->add($rule);

        $model = BizRule::find('own-name');
        $this->assertNotNull($model);
        $model->className = BizRuleTestAllowRule::class;

        $this->assertTrue($model->save());
        $this->assertFalse($model->hasErrors('name'));
        $this->assertInstanceOf(
            BizRuleTestAllowRule::class,
            $auth->getRule('own-name')
        );
    }

    /**
     * Regression F14-1: auth_rule.name is varchar(64) in the DB schema, but
     * SQLite does not enforce VARCHAR length — a 65+ char name used to pass
     * validation, get written to the DB and only blow up later on a strict
     * (MySQL/PgSQL) server. The 'string'/'max' => 64 rule must reject it
     * during validation with an error on 'name' (save false, nothing written).
     */
    public function testNameLongerThan64CharsFailsValidationWithoutSave()
    {
        $name65 = str_repeat('n', 65);

        $model = new BizRule(null);
        $model->name = $name65;
        $model->className = BizRuleTestDenyRule::class;

        $this->assertFalse($model->save());
        $this->assertTrue($model->hasErrors('name'));
        $this->assertStringContainsString('at most 64', $model->getFirstError('name'));
        $this->assertNull(Yii::$app->authManager->getRule($name65));
    }

    /**
     * Regression F14-1: a 64-char name is the longest one allowed by
     * auth_rule.name and must still validate and save.
     */
    public function testNameOfExactly64CharsStillValidates()
    {
        $name64 = str_repeat('n', 64);

        $model = new BizRule(null);
        $model->name = $name64;
        $model->className = BizRuleTestDenyRule::class;

        $this->assertTrue($model->save());
        $rule = Yii::$app->authManager->getRule($name64);
        $this->assertInstanceOf(BizRuleTestDenyRule::class, $rule);
        $this->assertSame($name64, $rule->name);
    }

    /**
     * Regression F14-1: the 'name' trim filter runs before validation and the
     * uniqueness check — a name padded with whitespace is stored trimmed (not
     * space-padded), and a whitespace-only name trims down to '' and fails
     * 'required' instead of creating a rule with a blank/spacey name.
     */
    public function testNameIsTrimmedBeforeValidation()
    {
        $model = new BizRule(null);
        $model->name = '  trimmed-rule  ';
        $model->className = BizRuleTestDenyRule::class;

        $this->assertTrue($model->save());
        $this->assertInstanceOf(
            BizRuleTestDenyRule::class,
            Yii::$app->authManager->getRule('trimmed-rule')
        );
        $this->assertNull(Yii::$app->authManager->getRule('  trimmed-rule  '));

        // whitespace-only name: trim empties it, 'required' must reject it
        $blank = new BizRule(null);
        $blank->name = '   ';
        $blank->className = BizRuleTestDenyRule::class;

        $this->assertFalse($blank->save());
        $this->assertTrue($blank->hasErrors('name'));
    }
}
