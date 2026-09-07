<?php

namespace mdm\admin\models\form;

use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use mdm\admin\components\Helper;
use mdm\admin\models\User;

/**
 * Login form
 */
class Login extends Model
{
    /**
     * Fixed bcrypt hash (cost 13, same as the default yii\base\Security
     * passwordHashCost) of a random non-secret passphrase. When the submitted
     * username matches no account the login must still run one bcrypt verify
     * against this dummy hash, otherwise the request short-circuits and the
     * response time reveals whether the account exists (user-enumeration via
     * timing oracle, audit QA wave-20 F20-3).
     */
    const DUMMY_PASSWORD_HASH = '$2y$13$3fBqUKXXdTG52aE9qQyUxufmye97JdvhqtnS4luu9lVrMQkc5tvoK';

    /**
     * Default maximum consecutive failed login attempts per IP+username pair
     * before a temporary lockout starts (audit QA wave-21 F21-1).
     */
    const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Default lockout duration in seconds (15 minutes). The window is FIXED:
     * further attempts made while locked are rejected before counting, so
     * hammering cannot slide/extend the lockout past this duration.
     */
    const LOCKOUT_SECONDS = 900;

    public $username;
    public $password;
    public $rememberMe = true;

    private $_user = false;

    /**
     * Guards the fail-open warning so a missing cache component is logged
     * only once per process instead of on every login attempt.
     */
    private static $_noCacheWarned = false;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if ($user === null) {
                // F20-3: account not found — still spend one bcrypt verify on a
                // fixed dummy hash so the request duration is indistinguishable
                // from the wrong-password path (no timing-based enumeration).
                Yii::$app->security->validatePassword($this->password, static::DUMMY_PASSWORD_HASH);
                $this->addError($attribute, 'Incorrect username or password.');
            } elseif (!$user->validatePassword($this->password)) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     *
     * Rate limiting (audit QA wave-21 F21-1): login attempts are throttled
     * per IP+username pair using the Yii cache. After MAX_FAILED_ATTEMPTS
     * consecutive failures the pair is locked out for LOCKOUT_SECONDS; while
     * locked the attempt is rejected BEFORE any password verification with a
     * clear message, and a successful login resets the failure counter.
     *
     * @param string|null $ip client IP address (the controller passes
     * Yii::$app->request->userIP; falls back to the request IP when null).
     * @return boolean whether the user is logged in successfully
     */
    public function login($ip = null)
    {
        $ip = $this->resolveClientIp($ip);

        // F21-1: lockout gate. Locked pairs are rejected up front with a clear
        // message — no bcrypt work is spent on locked attempts and, because
        // locked attempts are never counted, the lockout window stays FIXED
        // (hammering cannot extend it past LOCKOUT_SECONDS).
        $remaining = $this->isLockedOut($ip);
        if ($remaining !== false) {
            $minutes = max(1, (int) ceil($remaining / 60));
            $this->addError('password', Yii::t('rbac-admin', 'Too many failed login attempts. Please try again in {minutes} minute(s).', ['minutes' => $minutes]));
            return false;
        }

        if ($this->validate()) {
            // success — reset the failed-attempt counter for this pair
            $this->resetFailures($ip);
            return Yii::$app->getUser()->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 30 : 0);
        }

        // Only genuine wrong-credential attempts consume a slot: empty
        // submissions trip the 'required' rule and must not count against the
        // user. The counter keeps its sliding TTL so a handful of failures
        // spread over more than LOCKOUT_SECONDS never adds up to a lockout.
        if ($this->username !== null && trim((string) $this->username) !== ''
            && $this->password !== null && (string) $this->password !== '') {
            $this->registerFailedAttempt($ip);
        }

        return false;
    }

    /**
     * Maximum failed attempts before the temporary lockout kicks in.
     * Overridable via param `user.login.maxAttempts`.
     * @return int
     */
    public function maxFailedAttempts()
    {
        return (int) ArrayHelper::getValue(Yii::$app->params, 'user.login.maxAttempts', static::MAX_FAILED_ATTEMPTS);
    }

    /**
     * Lockout duration in seconds. Overridable via param
     * `user.login.lockoutSeconds`.
     * @return int
     */
    public function lockoutSeconds()
    {
        return (int) ArrayHelper::getValue(Yii::$app->params, 'user.login.lockoutSeconds', static::LOCKOUT_SECONDS);
    }

    /**
     * Checks whether this IP+username pair is currently locked out.
     *
     * Fail-open (documented): when no cache component is configured the
     * counter cannot be stored, so the check passes and a warning is logged
     * (once per process). This keeps the login page usable on misconfigured
     * hosts instead of bricking every account.
     *
     * @param string|null $ip client IP address.
     * @return int|false remaining lockout seconds when the pair is locked,
     * false when it may attempt a login.
     */
    public function isLockedOut($ip = null)
    {
        $cache = $this->getCache();
        if ($cache === null || $this->username === null || trim((string) $this->username) === '') {
            return false;
        }
        $ip = $this->resolveClientIp($ip);
        $until = $cache->get($this->lockoutKey($ip));
        if ($until === false) {
            return false;
        }
        $remaining = (int) $until - time();
        if ($remaining <= 0) {
            // expiry already passed — clean both entries defensively
            $cache->delete($this->lockoutKey($ip));
            $cache->delete($this->failureKey($ip));
            return false;
        }
        return $remaining;
    }

    /**
     * Counts one failed login attempt for this IP+username pair and starts the
     * fixed lockout window once the failure limit is reached.
     *
     * @param string|null $ip client IP address.
     */
    public function registerFailedAttempt($ip = null)
    {
        $cache = $this->getCache();
        if ($cache === null || $this->username === null || trim((string) $this->username) === '') {
            return;
        }
        $ip = $this->resolveClientIp($ip);
        $key = $this->failureKey($ip);
        // get+set is intentionally NOT atomic — the same trade-off as the
        // F20-4 reset throttle; a racing pair of requests can at worst lose
        // one slot, which cannot meaningfully weaken the lockout.
        $count = (int) $cache->get($key) + 1;
        $cache->set($key, $count, $this->lockoutSeconds());
        if ($count >= $this->maxFailedAttempts()) {
            $lockKey = $this->lockoutKey($ip);
            // only the FIRST crossing starts the window; while the lock entry
            // exists further failures are rejected before counting (login()),
            // so the window is never slid/extended by continued hammering.
            if ($cache->get($lockKey) === false) {
                $cache->set($lockKey, time() + $this->lockoutSeconds(), $this->lockoutSeconds());
                // F22-2: username/IP are user-supplied — sanitize before logging
                // so embedded newlines cannot forge extra log rows.
                Yii::warning('Lockout login sementara: username "' . Helper::sanitizeForLog($this->username)
                    . '" dari IP ' . Helper::sanitizeForLog($ip)
                    . ' setelah ' . $count . ' percobaan gagal (F21-1).', 'auth');
            }
        }
    }

    /**
     * Clears the failed-attempt counter and the lockout for this IP+username
     * pair. Called after a successful login so the counter starts fresh.
     *
     * @param string|null $ip client IP address.
     */
    public function resetFailures($ip = null)
    {
        $cache = Yii::$app->get('cache', false);
        if ($cache === null || $this->username === null || trim((string) $this->username) === '') {
            return;
        }
        $ip = $this->resolveClientIp($ip);
        $cache->delete($this->failureKey($ip));
        $cache->delete($this->lockoutKey($ip));
    }

    /**
     * Returns the application cache component, or null (with one warning per
     * process) when none is configured — the rate limiter then fails open.
     * @return \yii\caching\Cache|null
     */
    protected function getCache()
    {
        $cache = Yii::$app->get('cache', false);
        if ($cache === null && !static::$_noCacheWarned) {
            static::$_noCacheWarned = true;
            Yii::warning('Komponen "cache" tidak terkonfigurasi — rate limit percobaan login (F21-1) NONAKTIF (fail-open).', 'auth');
        }
        return $cache;
    }

    /**
     * Cache key of the consecutive-failure counter for this IP+username pair.
     * @param string $ip resolved client IP.
     * @return string
     */
    protected function failureKey($ip)
    {
        return 'mdm.admin.login.fail.' . md5((string) $ip) . '.' . md5(mb_strtolower(trim((string) $this->username)));
    }

    /**
     * Cache key of the lockout entry (value: unix timestamp until which the
     * pair is locked) for this IP+username pair.
     * @param string $ip resolved client IP.
     * @return string
     */
    protected function lockoutKey($ip)
    {
        return 'mdm.admin.login.lock.' . md5((string) $ip) . '.' . md5(mb_strtolower(trim((string) $this->username)));
    }

    /**
     * Resolves the client IP used for rate-limit keys.
     * @param string|null $ip explicit IP (controller-provided).
     * @return string
     */
    protected function resolveClientIp($ip)
    {
        if ($ip !== null && $ip !== '') {
            return (string) $ip;
        }
        $request = Yii::$app->has('request') ? Yii::$app->getRequest() : null;
        return $request instanceof \yii\web\Request ? (string) $request->getUserIP() : '';
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user === false) {
            $class = Yii::$app->getUser()->identityClass ? : 'mdm\admin\models\User';
            $this->_user = $class::findByUsername($this->username);
        }

        return $this->_user;
    }
}
