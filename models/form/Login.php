<?php

namespace mdm\admin\models\form;

use Yii;
use yii\base\Model;
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

    public $username;
    public $password;
    public $rememberMe = true;
    
    private $_user = false;

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
     * @return boolean whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            return Yii::$app->getUser()->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 30 : 0);
        } else {
            return false;
        }
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
