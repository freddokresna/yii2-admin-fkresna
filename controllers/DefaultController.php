<?php

namespace mdm\admin\controllers;

use Yii;

/**
 * DefaultController
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class DefaultController extends \yii\web\Controller
{

    /**
     * Action index
     */
    public function actionIndex($page = 'README.md')
    {
        // Validate route parameter before use (CWE-22 path traversal + CTE-18 XSS).
        // Only allow: docs/images/image{digit}.png, README.md, CHANGELOG.md,
        // CONTRIBUTING.md, LICENSE — nothing else.
        if (preg_match('/^docs\/images\/image\d+\.png$/', $page)) {
            $file = Yii::getAlias("@mdm/admin/{$page}");
            return Yii::$app->getResponse()->sendFile($file);
        }
        if (preg_match('/^(README|CHANGELOG|CONTRIBUTING|LICENSE)\.md$/i', $page)) {
            $file = Yii::getAlias("@mdm/admin/{$page}");
            if (is_file($file)) {
                return $this->render('index', ['page' => $page]);
            }
        }
        // Reject anything that doesn't match the allowlist.
        throw new \yii\web\NotFoundHttpException('Page not found.');
    }
}
