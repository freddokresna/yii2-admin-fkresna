<?php

namespace tests\codeception\unit\models;

use mdm\admin\models\Route;
use tests\codeception\unit\TestCase;
use Yii;

/**
 * Route (mdm\admin\models\Route) — route prefix/permission-name normalization
 * and the route list offered for assignment (controllers scanned from the
 * admin module of this repo).
 *
 * Pure code-path: no database is needed, route scanning only instantiates the
 * module controllers and reflects over their public action methods.
 */
class RouteTest extends TestCase
{
    public function testGetRouteList()
    {
        $route = new Route();

        // basic (non-advanced) scheme: prefix '/', names normalized to it
        $this->assertSame('/', $route->getRoutePrefix());
        $this->assertSame('/site/index', $route->getPermissionName('site/index'));
        $this->assertSame('/site/index', $route->getPermissionName('/site/index'));
        $this->assertSame('/site/index', $route->getPermissionName('//site/index/'));

        // every controller/action of the admin module must be discoverable
        $routes = $route->getAppRoutes(Yii::$app->getModule('admin'));
        $this->assertNotEmpty($routes);
        $expected = [
            '/admin/*',
            '/admin/default/*',
            '/admin/default/index',
            '/admin/assignment/*',
            '/admin/assignment/index',
            '/admin/assignment/view',
            '/admin/route/*',
            '/admin/route/index',
            '/admin/user/*',
            '/admin/user/index',
            '/admin/menu/*',
            '/admin/menu/index',
            '/admin/rule/*',
            '/admin/rule/index',
            '/admin/role/*',
            '/admin/role/index',
            '/admin/permission/*',
            '/admin/permission/index',
        ];
        foreach ($expected as $routeName) {
            $this->assertArrayHasKey($routeName, $routes, 'Route "' . $routeName . '" must be found by the scanner');
        }
    }
}
