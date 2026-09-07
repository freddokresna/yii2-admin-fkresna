<?php

namespace tests\codeception\unit\models;

use mdm\admin\components\UserStatus;
use mdm\admin\models\form\PasswordResetRequest;
use tests\codeception\unit\DbTestCase;
use Yii;
use yii\caching\ArrayCache;

/**
 * PasswordResetRequest — per-IP / per-email throttling and the inactive-
 * account no-mail guarantee.
 *
 * Regression F20-4: request-password-reset had no throttling at all, so an
 * attacker could probe arbitrary addresses as fast as the network allowed and
 * use the SMTP timing channel (sending mail is slow, the not-found path is
 * fast) to enumerate active accounts. The fix adds cache counters keyed by IP
 * AND by email: every request consumes one slot on both counters, and once
 * either counter exceeds the limit the request must NOT send any email and
 * the controller answers with the same uniform message (plus a delay).
 * Accounts that are not ACTIVE never receive a reset email and are
 * indistinguishable from unknown addresses (same silent false path).
 */
class PasswordResetRequestTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // counters need a real (in-memory) cache; the suite default is a
        // DummyCache that stores nothing
        Yii::$app->set('cache', new ArrayCache());

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

    public function testPerIpCounterAllowsLimitThenBlocks()
    {
        $model = new PasswordResetRequest();
        $model->email = 'ghost@example.com';

        for ($i = 1; $i <= PasswordResetRequest::THROTTLE_MAX_ATTEMPTS; $i++) {
            $this->assertTrue($model->consumeAttempt('10.0.0.1'), 'attempt ' . $i . ' must be allowed');
        }
        $this->assertFalse($model->consumeAttempt('10.0.0.1'), 'attempt past the limit must be blocked');

        // a different IP AND a different email sit on fresh counters and are
        // still allowed (same email from another IP is exhausted by the
        // shared email counter — covered by the next test)
        $other = new PasswordResetRequest();
        $other->email = 'other@example.com';
        $this->assertTrue($other->consumeAttempt('10.0.0.2'));
    }

    public function testEmailCounterIsSharedAcrossIps()
    {
        $a = new PasswordResetRequest();
        $a->email = 'a@example.com';
        for ($i = 1; $i <= PasswordResetRequest::THROTTLE_MAX_ATTEMPTS; $i++) {
            $this->assertTrue($a->consumeAttempt('9.9.9.9'));
        }

        // same IP, different email: the IP counter (already at the limit)
        // must block the request
        $b = new PasswordResetRequest();
        $b->email = 'b@example.com';
        $this->assertFalse($b->consumeAttempt('9.9.9.9'));

        // different IP, same previously-exhausted email counter must block too
        $c = new PasswordResetRequest();
        $c->email = 'a@example.com';
        $this->assertFalse($c->consumeAttempt('8.8.8.8'));

        // fresh IP + fresh email still allowed
        $d = new PasswordResetRequest();
        $d->email = 'b@example.com';
        $this->assertTrue($d->consumeAttempt('8.8.8.8'));
    }

    public function testLimitIsConfigurableViaParams()
    {
        Yii::$app->params['user.passwordResetRequest.maxAttempts'] = 2;
        try {
            $model = new PasswordResetRequest();
            $model->email = 'cfg@example.com';
            $this->assertTrue($model->consumeAttempt('1.1.1.1'));
            $this->assertTrue($model->consumeAttempt('1.1.1.1'));
            $this->assertFalse($model->consumeAttempt('1.1.1.1'));
        } finally {
            unset(Yii::$app->params['user.passwordResetRequest.maxAttempts']);
        }
    }

    public function testSendEmailNeverMailsInactiveAccount()
    {
        $db = Yii::$app->db;
        $now = time();
        $db->createCommand()->insert('user', [
            'id' => 1,
            'username' => 'sleepy',
            'auth_key' => 'k1',
            'password_hash' => 'h1',
            'password_reset_token' => null,
            'email' => 'inactive@example.com',
            'status' => UserStatus::INACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        $model = new PasswordResetRequest();
        $model->email = 'inactive@example.com';

        $this->assertFalse($model->sendEmail(), 'no email may be sent for an inactive account');
        $token = $db->createCommand('SELECT password_reset_token FROM "user" WHERE id = 1')->queryScalar();
        $this->assertNull($token, 'no reset token may be generated for an inactive account');
    }

    public function testSendEmailUnknownAddressFollowsSameSilentFalsePath()
    {
        $model = new PasswordResetRequest();
        $model->email = 'never-registered@example.com';

        $this->assertFalse($model->sendEmail(), 'unknown address must silently return false (uniform behaviour)');
    }
}
