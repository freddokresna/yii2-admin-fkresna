<?php
namespace mdm\admin\models\form;

use mdm\admin\components\UserStatus;
use mdm\admin\models\User;
use Yii;
use yii\base\Model;

/**
 * Password reset request form
 */
class PasswordResetRequest extends Model
{
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
            // sendEmail() mengembalikan false diam-diam bila user tak ditemukan.
        ];
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
