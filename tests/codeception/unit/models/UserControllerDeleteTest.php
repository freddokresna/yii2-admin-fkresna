<?php

namespace tests\codeception\unit\models;

use mdm\admin\controllers\UserController;
use mdm\admin\models\User;
use tests\codeception\unit\DbTestCase;
use Yii;
use yii\web\Response;

/**
 * UserController::actionDelete() — assignment revocation + self-delete guard.
 *
 * Regression F20-1:
 *  - the action used to delete the user row without revoking any
 *    auth_assignment. With no FK/ON DELETE CASCADE (and possibly a different
 *    authManager DB in split-DB setups) the orphan rows stayed behind and
 *    silently re-granted the old roles/permissions as soon as the primary key
 *    was reused by a new user (privilege leak). All assignments must be
 *    revoked via the authManager BEFORE the user row is deleted.
 *  - the action also allowed deleting the account that is currently logged
 *    in. It now refuses with an error flash and keeps the user intact.
 *
 * The user table is (re)created from the extension schema
 * (migrations/schema-sqlite.sql) because the User ActiveRecord queries it.
 */
class UserControllerDeleteTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = Yii::$app->db;
        $db->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        $db->createCommand(
            'CREATE TABLE "user" ('
            . '"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,'
            . '"username" varchar(32) NOT NULL,'
            . '"auth_key" varchar(32) NOT NULL,'
            . '"password_hash" varchar(256) NOT NULL,'
            . '"password_reset_token" varchar(256),'
            . '"email" varchar(256) NOT NULL,'
            . '"status" integer not null default 10,'
            . '"created_at" integer not null,'
            . '"updated_at" integer not null'
            . ')'
        )->execute();
        $now = time();
        $db->createCommand()->insert('user', [
            'id' => 1, 'username' => 'alice', 'auth_key' => 'k1',
            'password_hash' => 'h1', 'email' => 'alice@example.com',
            'status' => 10, 'created_at' => $now, 'updated_at' => $now,
        ])->execute();
        $db->createCommand()->insert('user', [
            'id' => 2, 'username' => 'bob', 'auth_key' => 'k2',
            'password_hash' => 'h2', 'email' => 'bob@example.com',
            'status' => 10, 'created_at' => $now, 'updated_at' => $now,
        ])->execute();

        // both users hold the same role + one direct permission assignment
        $auth = Yii::$app->authManager;
        $role = $auth->createRole('admin');
        $auth->add($role);
        $perm = $auth->createPermission('user/delete');
        $auth->add($perm);
        $auth->assign($role, 1);
        $auth->assign($perm, 1);
        $auth->assign($role, 2);
    }

    private function createController()
    {
        return new UserController('user', Yii::$app->getModule('admin'));
    }

    private function runDelete($id)
    {
        $controller = $this->createController();
        $old = Yii::$app->controller;
        // make the controller "active" so redirect(['index']) can resolve the
        // relative route (normally set by the app during runAction)
        Yii::$app->controller = $controller;
        try {
            return $controller->actionDelete($id);
        } finally {
            Yii::$app->controller = $old;
        }
    }

    public function testDeleteUserRevokesAllAssignmentsBeforeRemovingRow()
    {
        $auth = Yii::$app->authManager;
        $this->assertSame(['admin', 'user/delete'], array_keys($auth->getAssignments(1)));

        $response = $this->runDelete(1);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertNull(User::findOne(1), 'user row must be deleted');
        $this->assertSame([], $auth->getAssignments(1), 'no orphan auth_assignment may remain for the deleted user');
        $this->assertSame(['admin'], array_keys($auth->getAssignments(2)), 'other users assignments must be untouched');
    }

    public function testDeleteOwnAccountIsBlockedWithErrorFlash()
    {
        $session = new class extends \yii\web\Session {
            public $flashes = [];

            public function setFlash($key, $value = true, $removeAfterAccess = true)
            {
                $this->flashes[$key] = $value;
            }

            public function getFlash($key, $default = null, $delete = false)
            {
                return array_key_exists($key, $this->flashes) ? $this->flashes[$key] : $default;
            }

            public function hasFlash($key, $delete = false)
            {
                return array_key_exists($key, $this->flashes);
            }
        };
        // install an in-memory session so setFlash() works without starting a
        // real PHP session in the CLI test runner
        Yii::$app->set('session', $session);

        $auth = Yii::$app->authManager;
        $identity = User::findOne(2);
        Yii::$app->user->setIdentity($identity);
        try {
            $response = $this->runDelete(2);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertNotNull(User::findOne(2), 'self-delete must be refused, the user row stays');
            $this->assertSame(['admin'], array_keys($auth->getAssignments(2)), 'assignments stay intact when delete is refused');
            $this->assertTrue($session->hasFlash('error'));
            $this->assertStringContainsString('own account', (string) $session->getFlash('error'));
        } finally {
            // leave the (in-memory) session stub in place — no other unit test
            // touches the session component — and drop the logged-in identity
            Yii::$app->user->setIdentity(null);
        }
    }
}
