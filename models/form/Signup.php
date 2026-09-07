<?php
namespace mdm\admin\models\form;

use mdm\admin\components\UserStatus;
use mdm\admin\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Signup form
 */
class Signup extends Model
{
    public $username;
    public $email;
    public $password;
    public $retypePassword;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $class = Yii::$app->getUser()->identityClass ? : 'mdm\admin\models\User';
        return [
            ['username', 'filter', 'filter' => 'trim'],
            ['username', 'required'],
            ['username', 'unique', 'targetClass' => $class, 'message' => 'This username has already been taken.'],
            // F22-1: max must match the DB column (user.username varchar(32),
            // migrations/m160312_050000_create_user.php) — the old 255 let a
            // 33..255-char username pass validation and then explode in a DB
            // error on save() under strict SQL modes (PG/MySQL).
            ['username', 'string', 'min' => 2, 'max' => User::USERNAME_MAX_LENGTH],
            // F23-2: forbid control characters and Unicode separators. A
            // username with an embedded CR/LF or other \p{Cc}/\p{Cf} control
            // (or a U+2028/U+2029 separator) could otherwise be registered and
            // later forge log rows / corrupt UIs (CWE-117). The /u pattern
            // also rejects invalid UTF-8 outright (preg_match fails → error).
            ['username', 'match',
                'pattern' => '/^[^\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+$/u',
                // F24-2: keep the message in the rbac-admin catalog (en/id)
                // like every other rule message instead of a hardcoded string.
                'message' => Yii::t('rbac-admin', 'Username may not contain control characters, line breaks or separators.'),
            ],

            ['email', 'filter', 'filter' => 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'unique', 'targetClass' => $class, 'message' => 'This email address has already been taken.'],

            ['password', 'required'],
            ['password', 'string', 'min' => 6],

            ['retypePassword', 'required'],
            ['retypePassword', 'compare', 'compareAttribute' => 'password'],
        ];
    }

    /**
     * Signs user up.
     *
     * @return User|null the saved model or null if saving fails
     */
    public function signup()
    {
        if ($this->validate()) {
            $class = Yii::$app->getUser()->identityClass ? : 'mdm\admin\models\User';
            $user = new $class();
            $user->username = $this->username;
            $user->email = $this->email;
            $user->status = ArrayHelper::getValue(Yii::$app->params, 'user.defaultStatus', UserStatus::ACTIVE);
            $user->setPassword($this->password);
            $user->generateAuthKey();
            if ($user->save()) {
                return $user;
            }
        }

        return null;
    }
}
