<?php

namespace tests\codeception\unit\components;

use mdm\admin\components\Helper;
use tests\codeception\unit\DbTestCase;
use Yii;

/**
 * Helper::filter() — menu filtering by the routes assigned to a user.
 *
 * RBAC data is seeded through yii\rbac\DbManager into the suite test DB
 * (SQLite by default), so the whole non-strict route lookup path is exercised
 * without needing a database server or the web user component.
 */
class HelperTest extends DbTestCase
{
    public function testFilter()
    {
        // user '1' is author of route '/site/index' only
        $auth = Yii::$app->authManager;
        $perm = $auth->createPermission('/site/index');
        $auth->add($perm);
        $role = $auth->createRole('author');
        $auth->add($role);
        $auth->addChild($role, $perm);
        $auth->assign($role, '1');
        Helper::invalidate();

        $items = [
            ['label' => 'Home', 'url' => ['/site/index']],
            ['label' => 'About', 'url' => ['/site/about']],
            ['label' => 'Orphan', 'url' => '#'],
            ['label' => 'Menu Group', 'items' => [
                ['label' => 'Home 2', 'url' => ['/site/index']],
                ['label' => 'About 2', 'url' => ['/site/about']],
            ]],
            ['label' => 'Empty Group', 'items' => [
                ['label' => 'About 3', 'url' => ['/site/about']],
            ]],
        ];

        $filtered = Helper::filter($items, '1');

        // only items whose route is assigned to the user survive
        $this->assertSame(['Home', 'Menu Group'], array_map(function ($item) {
            return $item['label'];
        }, array_values($filtered)));

        // nested items are filtered recursively…
        $group = null;
        foreach ($filtered as $item) {
            if ($item['label'] === 'Menu Group') {
                $group = $item;
                break;
            }
        }
        $this->assertNotNull($group, 'Menu group with a visible child must be kept');
        $this->assertCount(1, $group['items']);
        $this->assertSame('Home 2', $group['items'][0]['label']);

        // …and a group whose children are all denied disappears entirely
        foreach ($filtered as $item) {
            $this->assertNotSame('Empty Group', $item['label']);
        }

        // plain '#' leaf without children is dropped even though it is "public"
        foreach ($filtered as $item) {
            $this->assertNotSame('Orphan', $item['label']);
        }

        // a user without any assignment sees nothing
        $this->assertSame([], Helper::filter($items, '2'));
    }
}
