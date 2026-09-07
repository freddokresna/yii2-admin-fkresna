<?php

namespace tests\codeception\unit\models;

use mdm\admin\models\Menu;
use tests\codeception\unit\DbTestCase;
use Yii;

/**
 * Menu (mdm\admin\models\Menu) — name length validation.
 *
 * Regression F14-2: menu.name is varchar(128) in the DB schema and the menu
 * form (views/menu/_form.php) only enforces the limit client-side
 * (maxlength). SQLite does not enforce VARCHAR length, so a 129-char name
 * used to pass server-side validation and get persisted — failing only later
 * on a strict (MySQL/PgSQL) server. The explicit 'string'/'max' => 128 rule
 * rejects it during validation with an error on 'name' (save false).
 *
 * The suite DB is SQLite by default (tests/codeception/config/db.php).
 * DbTestCase (re)creates the RBAC tables before every test; this test
 * additionally (re)creates the `menu` table with the same columns as the
 * extension's schema (migrations/schema-sqlite.sql), because Menu is an
 * ActiveRecord whose 'parent_name' in-rule queries the table on every
 * validate().
 *
 * Regression F15-2: menu.parent is an int FK to menu.id, but nothing server-
 * side validated it — a crafted POST with a non-numeric parent or an id that
 * matches no menu row used to pass (filterParent only ran on update) and the
 * row was persisted as an orphan (SQLite) or threw an IntegrityException/
 * HTTP 500 on strict servers that enforce the FK. 'integer' + 'exist' rules
 * now reject both during validation, and filterParent also runs on create.
 */
class MenuTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = Yii::$app->db;
        $db->createCommand('DROP TABLE IF EXISTS "menu"')->execute();
        $db->createCommand(
            'CREATE TABLE "menu" ('
            . '"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,'
            . '"name" varchar(128) NOT NULL,'
            . '"parent" int(11),'
            . '"route" varchar(256),'
            . '"order" int(11),'
            . '"data" LONGBLOB'
            . ')'
        )->execute();
    }

    /**
     * Regression F14-2: a 129-char menu name must fail validation with an
     * error on 'name' (SQLite would happily store it — varchar(128) is not
     * enforced there), so save() returns false and nothing is written.
     */
    public function testNameLongerThan128CharsFailsValidationWithoutSave()
    {
        $name129 = str_repeat('m', 129);

        $model = new Menu();
        $model->name = $name129;

        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('name'));
        $this->assertStringContainsString('at most 128', $model->getFirstError('name'));
        $this->assertFalse($model->save());
        $this->assertSame(0, (int) Menu::find()->count());
    }

    /**
     * Regression F14-2: a 128-char name is the longest one allowed by
     * menu.name and must still validate and persist.
     */
    public function testNameOfExactly128CharsStillValidatesAndSaves()
    {
        $name128 = str_repeat('m', 128);

        $model = new Menu();
        $model->name = $name128;

        $this->assertTrue($model->validate());
        $this->assertFalse($model->hasErrors('name'));
        $this->assertTrue($model->save());
        $this->assertSame(1, (int) Menu::find()->where(['name' => $name128])->count());
    }

    /**
     * Regression F15-2: a parent id that matches no menu row used to pass
     * validation (filterParent only ran on update and silently walked up to
     * NULL), then the row was persisted as an orphan (SQLite) or threw an
     * IntegrityException/500 on strict servers. The 'exist' rule must reject
     * it during validation: save false, error on 'parent', nothing written.
     */
    public function testParentWithUnknownIdFailsValidationWithoutSave()
    {
        $root = new Menu();
        $root->name = 'root';
        $this->assertTrue($root->save());

        $child = new Menu();
        $child->name = 'child';
        $child->parent = 999999;

        $this->assertFalse($child->validate());
        $this->assertTrue($child->hasErrors('parent'));
        $this->assertStringContainsString('not found', $child->getFirstError('parent'));
        $this->assertFalse($child->save());
        $this->assertSame(1, (int) Menu::find()->count(), 'no orphan row may be written');
    }

    /**
     * Regression F15-2: a non-numeric parent (crafted POST, e.g. 'abc') used
     * to be stored as-is and blow up later. The 'integer' rule must reject it
     * during validation with an error on 'parent' (no 500, no write).
     */
    public function testParentNonNumericFailsValidationWithoutSave()
    {
        $child = new Menu();
        $child->name = 'child';
        $child->parent = 'not-a-number';

        $this->assertFalse($child->validate());
        $this->assertTrue($child->hasErrors('parent'));
        $this->assertStringContainsString('integer', $child->getFirstError('parent'));
        $this->assertFalse($child->save());
        $this->assertSame(0, (int) Menu::find()->count());
    }

    /**
     * Regression F15-2 (positive control): a parent id that DOES exist must
     * still validate and save — on create too, where filterParent now also
     * runs and must stay silent for an acyclic chain.
     */
    public function testCreateWithExistingParentStillSaves()
    {
        $root = new Menu();
        $root->name = 'root';
        $this->assertTrue($root->save());

        $child = new Menu();
        $child->name = 'child';
        $child->parent = $root->id;

        $errors = implode(' | ', $child->getFirstErrors());
        $this->assertTrue($child->validate(), $errors);
        $this->assertFalse($child->hasErrors('parent'));
        $this->assertFalse($child->hasErrors('parent_name'));
        $this->assertTrue($child->save());
        $this->assertSame(2, (int) Menu::find()->count());
    }

    /**
     * Regression F15-2 (positive control): the menu form posts parent='' for
     * a top-level menu (no autocomplete selection); the 'default' filter
     * nulls it first, so the 'integer'/'exist' rules (skipOnEmpty) must not
     * reject a top-level create.
     */
    public function testTopLevelMenuWithEmptyParentStillSaves()
    {
        $menu = new Menu();
        $menu->name = 'top';
        $menu->parent = '';

        $errors = implode(' | ', $menu->getFirstErrors());
        $this->assertTrue($menu->validate(), $errors);
        $this->assertTrue($menu->save());

        $saved = Menu::find()->one();
        $this->assertNull($saved->parent);
    }
}
