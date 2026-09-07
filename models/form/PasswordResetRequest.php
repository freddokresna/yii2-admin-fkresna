<?php
namespace mdm\admin\models\form;

use mdm\admin\components\UserStatus;
use mdm\admin\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Password reset request form
 */
class PasswordResetRequest extends Model
{
    /**
     * Default throttle limit per IP / per email inside one window.
     */
    const THROTTLE_MAX_ATTEMPTS = 5;
    /**
     * Default throttle window in seconds (1 hour).
     */
    const THROTTLE_WINDOW = 3600;
    /**
     * Default extra delay (seconds) applied to a throttled request.
     */
    const THROTTLE_DELAY = 2;

    public $email;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['email', 'filter', 'filter' => 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            // TIDAK ada rule 'exist' — keberadaan akun tidak boleh dibedakan
            // lewat pesan validasi (anti user-enumeration, audit QA wave-3).
            // sendEmail() mengembalikan false diam-diam bila user tak ditemukan
            // ATAU akunnya nonaktif (status != ACTIVE) — kedua kasus menempuh
            // jalur yang sama, tanpa email.
        ];
    }

    /**
     * Consume one request attempt against the per-IP and per-email throttle
     * counters (cache). Both counters are incremented on every attempt; the
     * request is rejected once EITHER counter exceeds the limit.
     *
     * Timing-channel note (audit QA wave-20 F20-4): sending the reset email is
     * inherently slower than the no-user/no-active-account path, so a single
     * request still betrays whether the account is active — that residual
     * SMTP timing channel cannot be fully closed without actually sending a
     * mail on every request. The per-IP/per-email rate limit is the
     * compensating control: an enumerator can only probe a handful of
     * addresses per window before every further request is blocked (and
     * delayed) with the same uniform UI message, making bulk timing-based
     * enumeration impractical.
     *
     * @param string|null $ip client IP address (controller passes
     * Yii::$app->request->userIP).
     * @return bool true when the request may proceed, false when the attempt
     * limit was exceeded (the caller MUST NOT send any email then and SHOULD
     * still answer with the uniform message).
     */
    public function consumeAttempt($ip = null)
    {
        // Resolved lazily at call time so the counters work with whatever
        // cache the host app currently provides; no cache configured means no
        // counting is possible — fail open (single request is still bounded by
        // the mailer, and the uniform UI message stays in place).
        $cache = Yii::$app->get('cache', false);
        if ($cache === null) {
            return true;
        }

        $limit = (int) ArrayHelper::getValue(Yii::$app->params, 'user.passwordResetRequest.maxAttempts', static::THROTTLE_MAX_ATTEMPTS);
        $window = (int) ArrayHelper::getValue(Yii::$app->params, 'user.passwordResetRequest.windowSeconds', static::THROTTLE_WINDOW);

        $keys = [
            'mdm.admin.password-reset.ip.' . md5((string) $ip),
            'mdm.admin.password-reset.email.' . md5(mb_strtolower(trim((string) $this->email))),
        ];
        $blocked = false;
        foreach ($keys as $key) {
            $count = (int) $cache->get($key) + 1;
            $cache->set($key, $count, $window);
            if ($count > $limit) {
                $blocked = true;
            }
        }

        return !$blocked;
    }

    /**
     * Delay (seconds) applied to a throttled request so automated probing is
     * slowed down even further. 0 disables the extra delay.
     * @return int
     */
    public static function throttleDelay()
    {
        return (int) ArrayHelper::getValue(Yii::$app->params, 'user.passwordResetRequest.throttleDelay', static::THROTTLE_DELAY);
    }

    /**
     * Sends an email with a link, for resetting the password.
     *
     * @return boolean whether the email was send
     */
    public function sendEmail()
    {
        /* @var $user User */
        $class = Yii::$app->getUser()->identityClass ? : 'mdm\admin\models\User';
        // F20-4: hanya akun AKTIF yang dicari — email reset tidak pernah
        // dikirim ke akun nonaktif (status != ACTIVE), dan akun nonaktif
        // menempuh jalur return-false yang sama persis dengan email tak
        // terdaftar (tak ada perbedaan perilaku/kecepatan yang bisa diamati).
        $user = $class::findOne([
            'status' => UserStatus::ACTIVE,
            'email' => $this->email,
        ]);

        if ($user) {
            if (!ResetPassword::isPasswordResetTokenValid($user->password_reset_token)) {
                $user->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
            }

            if ($user->save()) {
                // `supportEmail` param opsional; fallback pakai host aplikasi.
                $host = Yii::$app->has('request') && Yii::$app->request instanceof \yii\web\Request
                    ? Yii::$app->request->serverName
                    : 'localhost';
                $supportEmail = Yii::$app->params['supportEmail'] ?? 'no-reply@' . ($host ?: 'localhost');

                return Yii::$app->mailer->compose(['html' => 'passwordResetToken-html', 'text' => 'passwordResetToken-text'], ['user' => $user])
                    ->setFrom([$supportEmail => Yii::$app->name . ' robot'])
                    ->setTo($this->email)
                    ->setSubject('Password reset for ' . Yii::$app->name)
                    ->send();
            }
        }

        return false;
    }
}
