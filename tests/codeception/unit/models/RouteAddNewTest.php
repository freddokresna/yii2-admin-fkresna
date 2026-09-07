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

    /**
     * F24-1: the rejection warning in addNew() interpolates the
     * attacker-supplied route name (POST via RouteController::actionCreate/
     * actionAssign). A route string longer than 64 bytes carrying an embedded
     * CRLF (audit payload: 63 x 'a' + CRLF + 'FORGED') must NOT be able to
     * forge extra log rows — capture the real warning and verify the emitted
     * log line stays single-line while the payload is still identifiable
     * (same rule family as F22-2/F23-1, Helper::sanitizeForLog).
     */
    public function testOverlongRouteWithCrlfIsRejectedAndLoggedSanitized()
    {
        $target = new class extends \yii\log\Target {
            public $captured = [];

            public function export()
            {
                $this->captured = array_merge($this->captured, $this->messages);
            }
        };

        Yii::getLogger()->flush(true); // drain anything queued by earlier tests
        Yii::$app->getLog()->targets = [$target];

        $auth = Yii::$app->authManager;
        $model = new Route();
        // 63 bytes + CRLF + 15 bytes + CRLF + 1 byte -> permission name
        // ('/' . route) is 82 bytes, far over the varchar(64) limit; the
        // embedded newlines are the log-injection payload.
        $evil = str_repeat('a', 63) . "\r\nFORGED-LOG-ROW\r\nb";
        try {
            $model->addNew([$evil]);
            Yii::getLogger()->flush(true);
        } finally {
            Yii::$app->getLog()->targets = [];
        }

        // rejected up-front, reported for UI feedback, nothing persisted
        $this->assertSame([$evil], $model->invalidRoutes, 'rejected route must be reported for UI feedback');
        $this->assertCount(0, $auth->getPermissions(), 'nothing may be persisted for an over-long route');

        $lines = [];
        foreach ($target->captured as $message) {
            if (isset($message[2]) && $message[2] === 'mdm\admin\models\Route::addNew'
                && strpos($message[0], 'Route "') === 0) {
                $lines[] = $message[0];
            }
        }
        $this->assertCount(1, $lines, 'exactly one rejection warning expected');

        // the value is still present and identifiable, but normalized…
        $this->assertStringContainsString('FORGED-LOG-ROW', $lines[0]);
        $this->assertStringContainsString('not added: permission name longer than 64 characters.', $lines[0]);
        // …and the log line contains no raw line break at all (CWE-117)
        $this->assertStringNotContainsString("\n", $lines[0]);
        $this->assertStringNotContainsString("\r", $lines[0]);
    }
}
