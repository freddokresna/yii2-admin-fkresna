<?php

namespace tests\codeception\unit\models;

use mdm\admin\models\form\Login;
use tests\codeception\unit\DbTestCase;
use Yii;
use yii\base\Security;
use yii\caching\ArrayCache;

/**
 * Login (mdm\admin\models\form\Login) — per-IP+username failed-attempt rate
 * limiting and temporary lockout.
 *
 * Regression F21-1: actionLogin had no attempt throttling at all, so an
 * online attacker could brute-force any account as fast as the network
 * allowed (F20-3 only equalised the timing channel, F20-4 only throttled the
 * password-reset flow). The fix counts consecutive failed attempts per
 * IP+username pair in the Yii cache: after MAX_FAILED_ATTEMPTS (5) failures
 * the pair is locked out for LOCKOUT_SECONDS (900 = 15 menit) with a clear
 * message; attempts made while locked are rejected BEFORE password
 * verification and are never counted, so hammering cannot extend the fixed
 * window. A successful login resets the failure counter. When no cache
 * component exists the limiter fails open (documented) so the login page is
 * never bricked by a misconfiguration.
 *
 * The suite default cache (DummyCache) stores nothing, so the tests install a
 * real in-memory ArrayCache — and a clock-controlled ArrayCache subclass for
 * the lockout-expiry test, so the 15-minute window can elapse deterministically
 * without sleeping.
 */
class LoginRateLimitTest extends DbTestCase
{
    /** Precomputed bcrypt hash of 'right-password' (cost 13, shared by all tests). */
    private static $passwordHash;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$passwordHash = (new Security())->generatePasswordHash('right-password');
    }

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
        $db->createCommand()->insert('user', [
            'id' => 1,
            'username' => 'alice',
            'auth_key' => 'auth-key-alice',
            'password_hash' => static::$passwordHash,
            'password_reset_token' => null,
            'email' => 'alice@example.com',
            'status' => 10,
            'created_at' => time(),
            'updated_at' => time(),
        ])->execute();
    }

    protected function tearDown(): void
    {
        // a successful login() sets the web-user identity on the shared app
        // instance; drop it so later tests start as guests
        Yii::$app->getUser()->logout(false);
        parent::tearDown();
    }

    /**
     * Runs one full login attempt through Login::login() — a fresh form per
     * attempt, exactly like the controller does per HTTP request.
     * @return Login the form used (check getFirstError()/hasErrors() on it)
     */
    private function attemptLogin($username, $password, $ip)
    {
        $form = new Login();
        $form->username = $username;
        $form->password = $password;
        $form->rememberMe = false;
        $form->login($ip);
        return $form;
    }

    public function testLockoutAfterMaxFailedAttemptsWithClearMessage()
    {
        $ip = '203.0.113.10';

        // attempts 1..5: wrong password — allowed (no lockout yet), uniform error
        for ($i = 1; $i <= Login::MAX_FAILED_ATTEMPTS; $i++) {
            $probe = new Login();
            $probe->username = 'alice';
            $this->assertFalse($probe->isLockedOut($ip), 'not locked before attempt ' . $i);
            $form = $this->attemptLogin('alice', 'wrong-password', $ip);
            $this->assertTrue($form->hasErrors('password'), 'attempt ' . $i . ' must fail');
            $this->assertStringContainsString('Incorrect username or password.', $form->getFirstError('password'));
        }

        // the 5th failure must have started the lockout
        $probe = new Login();
        $probe->username = 'alice';
        $remaining = $probe->isLockedOut($ip);
        $this->assertNotFalse($remaining, 'pair must be locked after ' . Login::MAX_FAILED_ATTEMPTS . ' failures');
        $this->assertGreaterThan(0, $remaining);

        // next attempt — even with the CORRECT password — is rejected up front
        // with the clear lockout message (gate runs before verification)
        $locked = $this->attemptLogin('alice', 'right-password', $ip);
        $this->assertStringContainsString('Too many failed login attempts.', $locked->getFirstError('password'));

        // a second wrong attempt while locked keeps the same clear message
        $locked2 = $this->attemptLogin('alice', 'wrong-password', $ip);
        $this->assertStringContainsString('Too many failed login attempts.', $locked2->getFirstError('password'));

        // lockout is per IP+username: the same user from another IP is untouched
        $other = new Login();
        $other->username = 'alice';
        $other->password = 'right-password';
        $other->rememberMe = false;
        $this->assertFalse($other->isLockedOut('198.51.100.77'));
        $this->assertTrue(
            $other->login('198.51.100.77'),
            'same user, different IP must still log in; errors: ' . json_encode($other->getErrors())
        );
    }

    public function testSuccessfulLoginResetsFailureCounter()
    {
        $ip = '203.0.113.20';

        // three failures — below the limit
        for ($i = 1; $i <= 3; $i++) {
            $form = $this->attemptLogin('alice', 'wrong-password', $ip);
            $this->assertTrue($form->hasErrors('password'));
        }
        $probe = new Login();
        $probe->username = 'alice';
        $this->assertFalse($probe->isLockedOut($ip), '3 failures must not lock the pair');

        // correct login succeeds AND resets the counter
        $ok = new Login();
        $ok->username = 'alice';
        $ok->password = 'right-password';
        $ok->rememberMe = false;
        $this->assertTrue($ok->login($ip));

        // four more failures after the success must NOT lock the pair — had the
        // counter not been reset, 3 + 4 = 7 >= 5 would already be locked
        for ($i = 1; $i <= 4; $i++) {
            $form = $this->attemptLogin('alice', 'wrong-password', $ip);
            $this->assertTrue($form->hasErrors('password'));
        }
        $probe = new Login();
        $probe->username = 'alice';
        $this->assertFalse($probe->isLockedOut($ip), 'counter must have been reset by the successful login');

        // and the pair can still log in with the right password
        $ok2 = new Login();
        $ok2->username = 'alice';
        $ok2->password = 'right-password';
        $ok2->rememberMe = false;
        $this->assertTrue($ok2->login($ip));
    }

    public function testLockoutEndsAfterWindowElapses()
    {
        // clock-controlled cache: advancing ->now expires entries exactly like
        // the passage of wall-clock time would
        $clock = new class extends ArrayCache {
            public $now = 1.0;
            private $_store = [];

            protected function setValue($key, $value, $duration)
            {
                $this->_store[$key] = [$value, $duration === 0 ? 0 : $this->now + $duration];
                return true;
            }

            protected function getValue($key)
            {
                if (!array_key_exists($key, $this->_store)) {
                    return false;
                }
                $expire = $this->_store[$key][1];
                if ($expire !== 0 && $expire <= $this->now) {
                    unset($this->_store[$key]);
                    return false;
                }
                return $this->_store[$key][0];
            }

            protected function deleteValue($key)
            {
                unset($this->_store[$key]);
                return true;
            }

            protected function flushValues()
            {
                $this->_store = [];
                return true;
            }
        };
        Yii::$app->set('cache', $clock);
        $ip = '203.0.113.30';

        // reach the lockout
        for ($i = 1; $i <= Login::MAX_FAILED_ATTEMPTS; $i++) {
            $form = $this->attemptLogin('alice', 'wrong-password', $ip);
            $this->assertTrue($form->hasErrors('password'));
        }
        $probe = new Login();
        $probe->username = 'alice';
        $this->assertNotFalse($probe->isLockedOut($ip), 'pair must be locked');

        // mid-window the pair stays locked and hammering does NOT extend the
        // window (locked attempts are rejected before counting)
        $clock->now += Login::LOCKOUT_SECONDS / 2;
        $mid = $this->attemptLogin('alice', 'wrong-password', $ip);
        $this->assertStringContainsString('Too many failed login attempts.', $mid->getFirstError('password'));
        $probe2 = new Login();
        $probe2->username = 'alice';
        $this->assertNotFalse($probe2->isLockedOut($ip), 'still locked mid-window');

        // once the full window has elapsed the lockout is over
        $clock->now += Login::LOCKOUT_SECONDS / 2 + 1;
        $probe3 = new Login();
        $probe3->username = 'alice';
        $this->assertFalse($probe3->isLockedOut($ip), 'lockout must end after the window');

        // and the legitimate user can log in again (counter starts fresh)
        $ok = new Login();
        $ok->username = 'alice';
        $ok->password = 'right-password';
        $ok->rememberMe = false;
        $this->assertTrue($ok->login($ip));
    }

    public function testNoWorkingCacheNeverLocksRealUsersOut()
    {
        // the suite default cache is a DummyCache that stores nothing; with it
        // the limiter can not count, so repeated failures must never lock a
        // pair and a correct login must keep working (fail-open behaviour)
        Yii::$app->set('cache', new \yii\caching\DummyCache());
        $ip = '203.0.113.40';

        for ($i = 1; $i <= Login::MAX_FAILED_ATTEMPTS + 3; $i++) {
            $form = $this->attemptLogin('alice', 'wrong-password', $ip);
            $this->assertTrue($form->hasErrors('password'));
        }
        $probe = new Login();
        $probe->username = 'alice';
        $this->assertFalse($probe->isLockedOut($ip), 'no counting possible without a real cache');

        $ok = new Login();
        $ok->username = 'alice';
        $ok->password = 'right-password';
        $ok->rememberMe = false;
        $this->assertTrue($ok->login($ip), 'correct login must still succeed when the cache stores nothing');
    }
}
