<?php
/**
 * Application configuration for unit tests
 */
return yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/config.php'),
    require(__DIR__ . '/web.php'),
    [
        // D-05: scanner route (Route::getAppRoutes) meng-instantiate tiap
        // controller — AssignmentController::init() butuh user.identityClass.
        'components' => [
            'user' => [
                'class' => 'yii\web\User',
                'identityClass' => 'mdm\admin\models\User',
                'enableSession' => false,
            ],
        ],
    ]
);
