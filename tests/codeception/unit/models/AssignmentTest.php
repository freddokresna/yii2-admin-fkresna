<?php

namespace tests\codeception\unit\models;

use mdm\admin\models\Assignment;
use tests\codeception\unit\DbTestCase;
use Yii;
use yii\base\ErrorException;

/**
 * Assignment (mdm\admin\models\Assignment) — getItems()/assign()/revoke()
 * for a user whose assignments include a *route* permission (name starts
 * with '/').
 *
 * Route permissions are intentionally not part of the "available" pool
 * (getItems() only offers roles and non-route permissions), yet a user may
 * legitimately hold a direct assignment to one. Before the fix the assigned
 * loop read $available[$item->roleName] unconditionally, so the undefined
 * array key surfaced as an ErrorException (HTTP 500 under the web error
 * handler) in assignment/view, assign and revoke.
 */
class AssignmentTest extends DbTestCase
{
    public function testGetItemsWithRoutePermissionAssignment()
    {
        // seed: one role, one plain permission and one route permission,
        // with the route permission assigned DIRECTLY to user '1'
        $auth = Yii::$app->authManager;

        $role = $auth->createRole('author');
        $auth->add($role);
        $perm = $auth->createPermission('createPost');
        $auth->add($perm);
        $routePerm = $auth->createPermission('/post/view');
        $auth->add($routePerm);
        $otherRoute = $auth->createPermission('/admin/assignment/index');
        $auth->add($otherRoute);

        $auth->assign($role, '1');
        $auth->assign($perm, '1');
        $auth->assign($routePerm, '1');

        // Yii's web ErrorHandler converts the undefined-key E_WARNING into an
        // ErrorException; mirror that so a regression fails the test instead
        // of being swallowed as a bare PHP warning.
        set_error_handler(static function ($severity, $message, $file, $line) {
            if ($severity === E_WARNING) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
            return false;
        });
        try {
            $model = new Assignment('1');
            $items = $model->getItems();
        } catch (ErrorException $e) {
            $this->fail('getItems() must not throw for a route-permission assignment: ' . $e->getMessage());
        } finally {
            restore_error_handler();
        }

        // the route permission is visible in 'assigned' (typed 'route') and
        // the role/plain permission keep their own types
        $this->assertArrayHasKey('/post/view', $items['assigned']);
        $this->assertSame('route', $items['assigned']['/post/view']);
        $this->assertSame('role', $items['assigned']['author']);
        $this->assertSame('permission', $items['assigned']['createPost']);

        // assigned items must no longer be offered as available, and route
        // permissions are never offered for assignment at all
        $this->assertArrayNotHasKey('author', $items['available']);
        $this->assertArrayNotHasKey('createPost', $items['available']);
        $this->assertArrayNotHasKey('/post/view', $items['available']);
        $this->assertArrayNotHasKey('/admin/assignment/index', $items['available']);
    }

    public function testRevokeAndAssignRoutePermission()
    {
        $auth = Yii::$app->authManager;
        $routePerm = $auth->createPermission('/post/delete');
        $auth->add($routePerm);
        $auth->assign($routePerm, '1');

        $model = new Assignment('1');
        $items = $model->getItems();
        $this->assertSame('route', $items['assigned']['/post/delete']);

        // revoke() accepts the route permission (getPermission fallback)
        $this->assertSame(1, $model->revoke(['/post/delete']));
        $items = $model->getItems();
        $this->assertArrayNotHasKey('/post/delete', $items['assigned']);
        $this->assertNull($auth->getAssignment('/post/delete', '1'));

        // assign() accepts it too, and it reappears in 'assigned' as 'route'
        $this->assertSame(1, $model->assign(['/post/delete']));
        $items = $model->getItems();
        $this->assertArrayHasKey('/post/delete', $items['assigned']);
        $this->assertSame('route', $items['assigned']['/post/delete']);
        $this->assertNotNull($auth->getAssignment('/post/delete', '1'));
    }
}
