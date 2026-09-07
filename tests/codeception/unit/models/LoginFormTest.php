<?php

namespace tests\codeception\unit\models;

use mdm\admin\models\form\Login;
use tests\codeception\unit\DbTestCase;
use Yii;
use yii\base\Security;

/**
 * Login (mdm\admin\models\form\Login) — uniform bcrypt work on the
 * unknown-user path.
 *
 * Regression F20-3: validatePassword() short-circuited with `!$user ||` when
 * the username matched no account, so a login attempt for a nonexistent user
 * returned visibly faster than a wrong password for an existing one — a
 * timing oracle for account enumeration. The fix runs one bcrypt verify
 * against a fixed dummy hash (Login::DUMMY_PASSWORD_HASH, cost 13 — the same
 * as the yii\base\Security default) whenever the user is not found, so both
 * paths spend one full bcrypt verification.
 *
 * The security component is swapped for a recording spy so the test can prove
 * WHICH hash the verify ran against.
 */
class LoginFormTest extends DbTestCase
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
    }

    private function createRecordingSecurity()
    {
        return new class extends Security {
            public $lastHash;
            public $lastPassword;

            public function validatePassword($password, $hash)
            {
                $this->lastPassword = $password;
                $this->lastHash = $hash;
                return parent::validatePassword($password, $hash);
            }
        };
    }

    public function testDummyHashUsesDefaultBcryptCost()
    {
        $security = new Security();
        $this->assertStringStartsWith(
            '$2y$' . $security->passwordHashCost . '$',
            Login::DUMMY_PASSWORD_HASH,
            'dummy hash cost must match the default passwordHashCost so both paths burn the same time'
        );
    }

    public function testUnknownUserStillRunsBcryptVerifyAgainstDummyHash()
    {
        $spy = $this->createRecordingSecurity();
        $old = Yii::$app->get('security', false);
        Yii::$app->set('security', $spy);
        try {
            $form = new Login();
            $form->username = 'no-such-user-xyz';
            $form->password = 'attacker-guess';

            $form->validatePassword('password', []);

            $this->assertTrue($form->hasErrors('password'));
            $this->assertStringContainsString('Incorrect username or password.', $form->getFirstError('password'));
            $this->assertNotNull($spy->lastHash, 'a bcrypt verify must have run even though the user does not exist');
            $this->assertSame(Login::DUMMY_PASSWORD_HASH, $spy->lastHash);
            $this->assertSame('attacker-guess', $spy->lastPassword);
        } finally {
            Yii::$app->set('security', $old);
        }
    }

    public function testKnownUserWithWrongPasswordStillFailsWithSameMessage()
    {
        $security = new Security();
        Yii::$app->db->createCommand()->insert('user', [
            'id' => 1,
            'username' => 'alice',
            'auth_key' => $security->generateRandomString(),
            'password_hash' => $security->generatePasswordHash('right-password'),
            'email' => 'alice@example.com',
            'status' => 10,
            'created_at' => time(),
            'updated_at' => time(),
        ])->execute();

        $spy = $this->createRecordingSecurity();
        $old = Yii::$app->get('security', false);
        Yii::$app->set('security', $spy);
        try {
            $form = new Login();
            $form->username = 'alice';
            $form->password = 'wrong-password';

            $form->validatePassword('password', []);

            $this->assertTrue($form->hasErrors('password'));
            $this->assertStringContainsString('Incorrect username or password.', $form->getFirstError('password'));
            // real user path verifies against the STORED hash (not the dummy)
            $storedHash = Yii::$app->db->createCommand('SELECT password_hash FROM "user" WHERE id = 1')->queryScalar();
            $this->assertSame($storedHash, $spy->lastHash);
        } finally {
            Yii::$app->set('security', $old);
        }
    }
}
