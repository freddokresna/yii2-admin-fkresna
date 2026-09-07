<?php

namespace tests\codeception\unit\models;

use mdm\admin\models\Route;
use tests\codeception\unit\DbTestCase;
use Yii;

/**
 * Route::addNew() — 64-character limit on the resulting permission name.
 *
 * Regression F20-2: auth_item.name is varchar(64) in the DB schema. MySQL
 * silently rejects a longer name (caught exception in addNew(), nothing
 * persisted — a silent fail), while SQLite stores it happily because it does
 * not enforce VARCHAR length. So the same input behaved differently per
 * server. addNew() now rejects permission names over 64 characters up-front
 * (same rule family as F14-1) and records the rejected route strings in
 * Route::$invalidRoutes so the controller can surface a visible UI error.
 */
class RouteAddNewTest extends DbTestCase
{
    public function testRouteLongerThan64CharsIsRejectedAndReported()
    {
        $auth = Yii::$app->authManager;
        $model = new Route();

        // 64 plain chars -> permission name '/' + 64 = 65 chars -> rejected
        $long = str_repeat('a', 64);
        $model->addNew([$long]);

        $this->assertSame([$long], $model->invalidRoutes, 'rejected route must be reported for UI feedback');
        $this->assertNull($auth->getPermission('/' . $long), 'nothing may be persisted for an over-long route');
        $this->assertCount(0, $auth->getPermissions());
    }

    public function testValidRouteStillAddedWhenListContainsAnInvalidOne()
    {
        $auth = Yii::$app->authManager;
        $model = new Route();

        $long = str_repeat('b', 64);
        $model->addNew(['/site/index', $long]);

        $this->assertSame([$long], $model->invalidRoutes);
        $this->assertNotNull($auth->getPermission('/site/index'), 'valid routes in the same batch must still be added');
        $this->assertNull($auth->getPermission('/' . $long));
        $this->assertArrayHasKey('/site/index', $auth->getPermissions());
    }

    public function testRouteOfExactly64CharsPermissionNameStillAdded()
    {
        $auth = Yii::$app->authManager;
        $model = new Route();

        // 63 plain chars -> permission name '/' + 63 = 64 chars -> longest
        // name the schema allows, must still be accepted
        $edge = str_repeat('c', 63);
        $model->addNew([$edge]);

        $this->assertSame([], $model->invalidRoutes);
        $this->assertNotNull($auth->getPermission('/' . $edge));
    }

    public function testOverlongParameterizedRouteIsRejectedToo()
    {
        $auth = Yii::$app->authManager;
        $model = new Route();

        // parameterized route whose action segment alone exceeds 64 chars
        // (the action is stored as its own auth_item permission as well)
        $long = str_repeat('d', 63) . '/index?p=1';
        $model->addNew([$long]);

        $this->assertSame([$long], $model->invalidRoutes);
        $this->assertCount(0, $auth->getPermissions());
    }
}
