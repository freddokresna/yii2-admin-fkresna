<?php

namespace tests\codeception\unit\models;

use mdm\admin\models\form\Signup;
use mdm\admin\models\User;
use tests\codeception\unit\DbTestCase;
use Yii;

/**
 * Signup (mdm\admin\models\form\Signup) — username length bound must match the
 * `user.username` DB column.
 *
 * Regression F22-1: the form allowed up to 255 chars while the column is
 * varchar(32) (migrations/m160312_050000_create_user.php). A 33..255-char
 * username passed validation and then died in a DB error on save() under
 * strict SQL modes (PG/MySQL 500). The string rule now caps at
 * User::USERNAME_MAX_LENGTH (32), so oversized names are rejected with a
 * normal username validation error BEFORE any DB write.
 */
class SignupTest extends DbTestCase
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
        $db->createCommand()->insert('user', [
            'id' => 1,
            'username' => 'alice',
            'auth_key' => 'auth-key-alice',
            'password_hash' => 'x',
            'password_reset_token' => null,
            'email' => 'alice@example.com',
            'status' => 10,
            'created_at' => time(),
            'updated_at' => time(),
        ])->execute();
    }

    public function testColumnLengthConstantMatchesMigration()
    {
        // the rule bound and the migration (varchar(32)) must stay in sync
        $this->assertSame(32, User::USERNAME_MAX_LENGTH);
    }

    public function testUsernameLongerThanColumnIsRejected()
    {
        $long = str_repeat('u', User::USERNAME_MAX_LENGTH + 1); // 33 chars
        $this->assertGreaterThan(User::USERNAME_MAX_LENGTH, strlen($long));

        $form = new Signup();
        $form->username = $long;
        $form->email = 'bob-33@example.com';
        $form->password = 'secret123';
        $form->retypePassword = 'secret123';

        // validation must fail on username with a max-length error …
        $this->assertFalse($form->validate(), '33-char username must not validate');
        $this->assertTrue($form->hasErrors('username'), 'error must be on username');
        $this->assertStringContainsString(
            'at most ' . User::USERNAME_MAX_LENGTH . ' characters',
            $form->getFirstError('username')
        );

        // … and signup() must return null (no DB write, no 500)
        $this->assertNull($form->signup());
        $this->assertNull(
            User::findByUsername($long),
            'no user with the oversized username may be persisted'
        );
    }

    public function testUsernameAtMaxColumnLengthIsAccepted()
    {
        $max = str_repeat('u', User::USERNAME_MAX_LENGTH); // exactly 32 chars
        $email = 'bob-max@example.com';

        $form = new Signup();
        $form->username = $max;
        $form->email = $email;
        $form->password = 'secret123';
        $form->retypePassword = 'secret123';

        $user = $form->signup();
        $this->assertNotNull($user, '32-char username (column limit) must sign up');
        $this->assertFalse($form->hasErrors());
        $this->assertNotNull($user->id, 'user must be persisted');
        $this->assertSame($max, $user->username);
        $this->assertNotNull(User::findByUsername($max), 'saved user must be findable by username');
    }
}
