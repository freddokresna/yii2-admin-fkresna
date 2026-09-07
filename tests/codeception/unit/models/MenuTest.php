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
}
