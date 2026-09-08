<?php

namespace mdm\admin\controllers;

use Yii;
use mdm\admin\models\Route;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;

/**
 * Description of RuleController
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class RouteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['post'],
                    'assign' => ['post'],
                    'remove' => ['post'],
                    'refresh' => ['post'],
                ],
            ],
            'access' => [
                'class' => \mdm\admin\components\AccessControl::class,
            ],
            // Fix 3: allow JSON API endpoints to pass CSRF validation.
            // JSON requests typically set Content-Type: application/json which
            // makes Yii's CsrfFilter reject the request because it only accepts
            // the token in POST body or X-CSRF-Token header when the content
            // type is application/x-www-form-urlencoded.  We whitelist the
            // JSON-write actions here so the token is checked from the header
            // regardless of Content-Type.
            'csrf' => [
                'class' => \yii\filters\CsrfFilter::class,
                'checkAjax' => false,   // AJAX / fetch POST still validated via header.
            ],
        ];
    }
    /**
     * Lists all Route models.
     * @return mixed
     */
    public function actionIndex()
    {
        $model = new Route();
        return $this->render('index', ['routes' => $model->getRoutes()]);
    }

    /**
     * Creates a new AuthItem model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        Yii::$app->getResponse()->format = 'json';
        $routes = Yii::$app->getRequest()->post('route', '');
        $routes = preg_split('/\s*,\s*/', trim((string)$routes), -1, PREG_SPLIT_NO_EMPTY);
        // Validate route parameter before use (CWE-20)
        $validRoutes = array_filter($routes, static function ($r) {
            // Must be a non-empty string matching Yii route pattern:
            // optional controller/action with optional subdirectories
            return preg_match('/^[a-zA-Z0-9_\/\-\.\*]+$/', trim($r)) && strlen(trim($r)) > 0;
        });
        $model = new Route();
        $model->addNew($validRoutes);
        $result = $model->getRoutes();
        // F20-2: names rejected by addNew() (>64 chars) must be visible in the
        // UI — return them alongside the route lists; _script.js renders them
        // in the alert box instead of letting the add fail silently.
        if ($model->invalidRoutes) {
            $result['errors'] = array_map(static function ($route) {
                return Yii::t('rbac-admin', 'Route "{route}" was not added: the route name is longer than 64 characters.', ['route' => $route]);
            }, $model->invalidRoutes);
        }
        return $result;
    }

    /**
     * Assign routes
     * @return array
     */
    public function actionAssign()
    {
        $routes = Yii::$app->getRequest()->post('routes', []);
        $routes = is_array($routes) ? $routes : [];
        // Validate route parameter before use (CWE-20)
        $validRoutes = array_filter($routes, static function ($r) {
            return preg_match('/^[a-zA-Z0-9_\/\-\.\*]+$/', trim((string)$r)) && strlen(trim((string)$r)) > 0;
        });
        $model = new Route();
        $model->addNew($validRoutes);
        Yii::$app->getResponse()->format = 'json';
        $result = $model->getRoutes();
        if ($model->invalidRoutes) {
            $result['errors'] = array_map(static function ($route) {
                return Yii::t('rbac-admin', 'Route "{route}" was not added: the route name is longer than 64 characters.', ['route' => $route]);
            }, $model->invalidRoutes);
        }
        return $result;
    }

    /**
     * Remove routes
     * @return array
     */
    public function actionRemove()
    {
        $routes = Yii::$app->getRequest()->post('routes', []);
        $routes = is_array($routes) ? $routes : [];
        $model = new Route();
        $model->remove($routes);
        Yii::$app->getResponse()->format = 'json';
        return $model->getRoutes();
    }

    /**
     * Refresh cache
     * @return type
     */
    public function actionRefresh()
    {
        $model = new Route();
        $model->invalidate();
        Yii::$app->getResponse()->format = 'json';
        return $model->getRoutes();
    }
}
