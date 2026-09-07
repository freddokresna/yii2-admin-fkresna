<?php

namespace mdm\admin\controllers;

use mdm\admin\components\Configs;
use mdm\admin\components\Helper;
use mdm\admin\components\UserStatus;
use mdm\admin\models\form\ChangePassword;
use mdm\admin\models\form\Login;
use mdm\admin\models\form\PasswordResetRequest;
use mdm\admin\models\form\ResetPassword;
use mdm\admin\models\form\Signup;
use mdm\admin\models\searchs\User as UserSearch;
use mdm\admin\models\User;
use Yii;
use yii\base\InvalidParamException;
use yii\base\UserException;
use yii\filters\VerbFilter;
use yii\mail\BaseMailer;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * User controller
 */
class UserController extends Controller
{
    private $_oldMailPath;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'logout' => ['post'],
                    'activate' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            if (Yii::$app->has('mailer') && ($mailer = Yii::$app->getMailer()) instanceof BaseMailer) {
                $this->_oldMailPath = method_exists($mailer, 'getViewPath') ? $mailer->getViewPath() : null;
                if ($this->_oldMailPath !== null && method_exists($mailer, 'setViewPath')) {
                    $mailer->setViewPath('@mdm/admin/mail');
                }
            }
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        if ($this->_oldMailPath !== null) {
            $mailer = Yii::$app->getMailer();
            if (method_exists($mailer, 'setViewPath')) {
                $mailer->setViewPath($this->_oldMailPath);
            }
        }
        return parent::afterAction($action, $result);
    }

    /**
     * Lists all User models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
                'searchModel' => $searchModel,
                'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single User model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
                'model' => $this->findModel($id),
        ]);
    }

    /**
     * Deletes an existing User model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        // F20-1: never delete the account that is currently logged in — an
        // operator could otherwise lock themselves (or the whole admin) out.
        $identity = Yii::$app->getUser()->getIdentity();
        if ($identity !== null && (string) $identity->getId() === (string) $model->id) {
            Yii::$app->getSession()->setFlash('error', Yii::t('rbac-admin', 'You can not delete your own account.'));
            return $this->redirect(['index']);
        }

        // F20-1: revoke ALL auth assignments BEFORE deleting the user. The
        // authManager may live on a different DB than the user table (split-DB
        // setup), so there is no cross-DB FK/ON DELETE CASCADE to clean up.
        // Deleting first would leave orphan auth_assignment rows behind that
        // silently grant the old permissions again as soon as the primary key
        // is reused by a new user (privilege leak). Revoking first keeps the
        // auth tables clean even if the user delete itself later fails.
        // revokeAll() (ManagerInterface) removes every assignment of the user
        // in one operation — roles, permissions and direct route assignments.
        $auth = Configs::authManager();
        if ($auth !== null) {
            $auth->revokeAll($model->id);
        }

        $model->delete();

        return $this->redirect(['index']);
    }

    /**
     * Login
     * @return string
     */
    public function actionLogin()
    {
        if (!Yii::$app->getUser()->isGuest) {
            return $this->goHome();
        }

        $model = new Login();
        // F21-1: pass the client IP so failed attempts are throttled per
        // IP+username pair (cache counter; 5 gagal -> lockout 15 menit).
        if ($model->load(Yii::$app->getRequest()->post()) && $model->login(Yii::$app->getRequest()->getUserIP())) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                    'model' => $model,
            ]);
        }
    }

    /**
     * Logout
     * @return string
     */
    public function actionLogout()
    {
        Yii::$app->getUser()->logout();

        return $this->goHome();
    }

    /**
     * Signup new user
     * @return string
     */
    public function actionSignup()
    {
        $model = new Signup();
        if ($model->load(Yii::$app->getRequest()->post())) {
            if ($user = $model->signup()) {
                return $this->goHome();
            }
        }

        return $this->render('signup', [
                'model' => $model,
        ]);
    }

    /**
     * Request reset password
     * @return string
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequest();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->validate()) {
            // F20-4: throttle per IP & per email (cache counter + jeda).
            // Setiap request memakan satu slot dari kedua counter; setelah
            // batas terlampaui, request TIDAK mengirim email apa pun, ditunda
            // (jeda) sejenak, lalu tetap menjawab dengan pesan sukses seragam —
            // pemanggil tak bisa membedakan akun terdaftar/nonaktif/tak ada,
            // dan enumerasi massal via kanal waktu SMTP dibatasi lajunya
            // (lihat catatan timing-channel di PasswordResetRequest::consumeAttempt()).
            $ip = Yii::$app->getRequest()->getUserIP();
            if ($model->consumeAttempt($ip)) {
                // Anti user-enumeration: hasil kirim email TIDAK dibedakan di UI —
                // selalu flash sukses generik. Kegagalan (email tak terdaftar /
                // akun nonaktif / mailer error) hanya dicatat di log.
                if (!$model->sendEmail()) {
                    // F22-2: email/IP are user-supplied — sanitize before
                    // logging so embedded newlines cannot forge log rows.
                    Yii::info('Password reset request tanpa email terkirim: ' . Helper::sanitizeForLog($model->email), 'auth');
                }
            } else {
                Yii::warning('Password reset request diblokir oleh rate limit: ' . Helper::sanitizeForLog($model->email)
                    . ' dari IP ' . Helper::sanitizeForLog($ip), 'auth');
                $delay = PasswordResetRequest::throttleDelay();
                if ($delay > 0) {
                    sleep($delay);
                }
            }
            Yii::$app->getSession()->setFlash('success', 'Jika email terdaftar, tautan reset password telah dikirim.');

            return $this->goHome();
        }

        return $this->render('requestPasswordResetToken', [
                'model' => $model,
        ]);
    }

    /**
     * Reset password
     * @return string
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPassword($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->getRequest()->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->getSession()->setFlash('success', 'New password was saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
                'model' => $model,
        ]);
    }

    /**
     * Reset password
     * @return string
     */
    public function actionChangePassword()
    {
        $model = new ChangePassword();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->change()) {
            return $this->goHome();
        }

        return $this->render('change-password', [
                'model' => $model,
        ]);
    }

    /**
     * Activate new user
     * @param integer $id
     * @return type
     * @throws UserException
     * @throws NotFoundHttpException
     */
    public function actionActivate($id)
    {
        /* @var $user User */
        $user = $this->findModel($id);
        if ($user->status == UserStatus::INACTIVE) {
            $user->status = UserStatus::ACTIVE;
            if ($user->save()) {
                return $this->goHome();
            } else {
                $errors = $user->firstErrors;
                throw new UserException(reset($errors));
            }
        }
        return $this->goHome();
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
