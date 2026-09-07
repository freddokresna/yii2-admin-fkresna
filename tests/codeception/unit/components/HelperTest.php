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

    /**
     * F22-2: Helper::sanitizeForLog() must collapse newlines/CRs/control
     * characters and repeated whitespace to single spaces so a user-supplied
     * identifier interpolated into a log line can never forge extra rows.
     */
    public function testSanitizeForLog()
    {
        // classic log-injection payloads (CRLF, lone LF/CR, tabs)
        $this->assertSame(
            'alice bob',
            Helper::sanitizeForLog("alice\r\nbob"),
            'CRLF must collapse to one space'
        );
        $this->assertSame(
            'alice bob',
            Helper::sanitizeForLog("alice\nbob"),
            'LF must collapse to one space'
        );
        $this->assertSame(
            'alice bob',
            Helper::sanitizeForLog("alice\rbob"),
            'CR must collapse to one space'
        );

        // repeated whitespace is collapsed and the result is trimmed
        $this->assertSame(
            'ali evil ce',
            Helper::sanitizeForLog("  ali \t  evil\n\n  ce  "),
            'whitespace runs collapse, edges are trimmed'
        );

        // escape/control characters (terminal/ANSI injection) are neutralized
        $this->assertSame(
            'a [2Jb',
            Helper::sanitizeForLog("a\x1b[2Jb"),
            'ESC must not survive into the log line'
        );

        // Unicode line/paragraph separators are neutralized too
        $this->assertSame(
            'a b c',
            Helper::sanitizeForLog("a\u{2028}b\u{2029}c"),
            'U+2028/U+2029 must not survive into the log line'
        );

        // clean values pass through untouched; scalars are cast to string
        $this->assertSame('alice', Helper::sanitizeForLog('alice'));
        $this->assertSame('203.0.113.7', Helper::sanitizeForLog('203.0.113.7'));
        $this->assertSame('42', Helper::sanitizeForLog(42));
        $this->assertSame('', Helper::sanitizeForLog(null));

        // the sanitized output never contains a line break
        $this->assertStringNotContainsString("\n", Helper::sanitizeForLog("x\n y \r\n z"));
    }

    /**
     * F23-1: invalid UTF-8 must NEVER fall back to the raw string. The /u
     * pattern cannot run on malformed input, and the old code then returned
     * the original bytes — so "\xFF\r\nFORGED" (the %FF%0D%0AFORGED payload)
     * smuggled CRLF into the log line (CWE-117). Sanitization must scrub the
     * invalid bytes and still collapse CR/LF.
     */
    public function testSanitizeForLogInvalidUtf8NeverReturnsRaw()
    {
        // the audit payload: invalid byte 0xFF followed by a CRLF forgery
        $payload = "\xFF\r\nFORGED";
        $this->assertFalse((bool) @preg_match('//u', $payload), 'payload must really be invalid UTF-8');

        $out = Helper::sanitizeForLog($payload);
        $this->assertSame('FORGED', $out, 'invalid \xFF byte is dropped and CRLF collapses');
        $this->assertStringNotContainsString("\n", $out, 'no LF may survive invalid UTF-8');
        $this->assertStringNotContainsString("\r", $out, 'no CR may survive invalid UTF-8');

        // CRLF-only forgery after a malformed lead byte
        $out2 = Helper::sanitizeForLog("evil\xC3\x28\r\nadmin");
        $this->assertStringNotContainsString("\n", $out2);
        $this->assertStringNotContainsString("\r", $out2);
        $this->assertStringNotContainsString("\x00", $out2);

        // invalid bytes alone (no CRLF) still yield a clean, lossy result
        $this->assertSame('alice', Helper::sanitizeForLog("ali\xFFce\xFE"));

        // sanity: the raw string must never be returned verbatim
        $this->assertNotSame($payload, Helper::sanitizeForLog($payload));
    }
}
