<?php

namespace mdm\admin\components;

use mdm\admin\models\Route;
use Yii;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;
use yii\web\User;

/**
 * Description of Helper
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 2.3
 */
class Helper
{
    private static $_userRoutes = [];
    private static $_defaultRoutes;
    private static $_routes;

    public static function getRegisteredRoutes()
    {
        if (self::$_routes === null) {
            self::$_routes = [];
            $manager = Configs::authManager();
            foreach ($manager->getPermissions() as $item) {
                if (is_string($item->name) && $item->name !== '' && $item->name[0] === '/') {
                    self::$_routes[$item->name] = $item->name;
                }
            }
        }
        return self::$_routes;
    }

    /**
     * Get assigned routes by default roles
     * @return array
     */
    protected static function getDefaultRoutes()
    {
        if (self::$_defaultRoutes === null) {
            $manager = Configs::authManager();
            $roles = $manager->defaultRoles;
            $cache = Configs::cache();
            if ($cache && ($routes = $cache->get($roles)) !== false) {
                self::$_defaultRoutes = $routes;
            } else {
                $permissions = self::$_defaultRoutes = [];
                foreach ($roles as $role) {
                    $permissions = array_merge($permissions, $manager->getPermissionsByRole($role));
                }
                foreach ($permissions as $item) {
                    if (is_string($item->name) && $item->name !== '' && $item->name[0] === '/') {
                        self::$_defaultRoutes[$item->name] = true;
                    }
                }
                if ($cache) {
                    $cache->set($roles, self::$_defaultRoutes, Configs::cacheDuration(), new TagDependency([
                        'tags' => Configs::CACHE_TAG,
                    ]));
                }
            }
        }
        return self::$_defaultRoutes;
    }

    /**
     * Get assigned routes of user.
     * @param integer $userId
     * @return array
     */
    public static function getRoutesByUser($userId)
    {
        if (!isset(self::$_userRoutes[$userId])) {
            $cache = Configs::cache();
            if ($cache && ($routes = $cache->get([__METHOD__, $userId])) !== false) {
                self::$_userRoutes[$userId] = $routes;
            } else {
                $routes = static::getDefaultRoutes();
                $manager = Configs::authManager();
                foreach ($manager->getPermissionsByUser($userId) as $item) {
                    if (is_string($item->name) && $item->name !== '' && $item->name[0] === '/') {
                        $routes[$item->name] = true;
                    }
                }
                self::$_userRoutes[$userId] = $routes;
                if ($cache) {
                    $cache->set([__METHOD__, $userId], $routes, Configs::cacheDuration(), new TagDependency([
                        'tags' => Configs::CACHE_TAG,
                    ]));
                }
            }
        }
        return self::$_userRoutes[$userId];
    }

    /**
     * Check access route for user.
     * @param string|array $route
     * @param integer|User $user
     * @return boolean
     */
    public static function checkRoute($route, $params = [], $user = null)
    {
        $config = Configs::instance();
        $r = static::normalizeRoute($route, $config->advanced);
        if ($config->onlyRegisteredRoute && !isset(static::getRegisteredRoutes()[$r])) {
            return true;
        }

        if ($user === null) {
            $user = Yii::$app->getUser();
        }
        $userId = $user instanceof User ? $user->getId() : $user;

        if ($config->strict) {
            if ($user->can($r, $params)) {
                return true;
            }
            while (($pos = strrpos($r, '/')) > 0) {
                $r = substr($r, 0, $pos);
                if ($user->can($r . '/*', $params)) {
                    return true;
                }
            }
            return $user->can('/*', $params);
        } else {
            $routes = static::getRoutesByUser($userId);
            if (isset($routes[$r])) {
                return true;
            }
            while (($pos = strrpos($r, '/')) > 0) {
                $r = substr($r, 0, $pos);
                if (isset($routes[$r . '/*'])) {
                    return true;
                }
            }
            return isset($routes['/*']);
        }
    }

    /**
     * Normalize route
     * @param  string  $route    Plain route string
     * @param  boolean|array $advanced Array containing the advanced configuration. Defaults to false.
     * @return string            Normalized route string
     */
    protected static function normalizeRoute($route, $advanced = false)
    {
        if ($route === '') {
            $normalized = '/' . Yii::$app->controller->getRoute();
        } elseif (strncmp($route, '/', 1) === 0) {
            $normalized = $route;
        } elseif (strpos($route, '/') === false) {
            $normalized = '/' . Yii::$app->controller->getUniqueId() . '/' . $route;
        } elseif (($mid = Yii::$app->controller->module->getUniqueId()) !== '') {
            $normalized = '/' . $mid . '/' . $route;
        } else {
            $normalized = '/' . $route;
        }
        // Prefix @app-id to route.
        if ($advanced) {
            $normalized = Route::PREFIX_ADVANCED . Yii::$app->id . $normalized;
        }
        return $normalized;
    }

    /**
     * Filter menu items
     * @param array $items
     * @param integer|User $user
     */
    public static function filter($items, $user = null)
    {
        if ($user === null) {
            $user = Yii::$app->getUser();
        }
        return static::filterRecursive($items, $user);
    }

    /**
     * Filter menu recursive
     * @param array $items
     * @param integer|User $user
     * @return array
     */
    protected static function filterRecursive($items, $user)
    {
        $result = [];
        foreach ($items as $i => $item) {
            $url = ArrayHelper::getValue($item, 'url', '#');
            $allow = is_array($url) ? static::checkRoute($url[0], array_slice($url, 1), $user) : true;

            if (isset($item['items']) && is_array($item['items'])) {
                $subItems = self::filterRecursive($item['items'], $user);
                if (count($subItems)) {
                    $allow = true;
                }
                $item['items'] = $subItems;
            }
            if ($allow && !($url == '#' && empty($item['items']))) {
                $result[$i] = $item;
            }
        }
        return $result;
    }

    /**
     * Filter action column button. Use with [[yii\grid\GridView]]
     * ```php
     * 'columns' => [
     *     ...
     *     [
     *         'class' => 'yii\grid\ActionColumn',
     *         'template' => Helper::filterActionColumn(['view','update','activate'])
     *     ]
     * ],
     * ```
     * @param array|string $buttons
     * @param integer|User $user
     * @return string
     */
    public static function filterActionColumn($buttons = [], $user = null)
    {
        if (is_array($buttons)) {
            $result = [];
            foreach ($buttons as $button) {
                if (static::checkRoute($button, [], $user)) {
                    $result[] = "{{$button}}";
                }
            }
            return implode(' ', $result);
        }
        return preg_replace_callback('/\\{([\w\-\/]+)\\}/', function ($matches) use ($user) {
            return static::checkRoute($matches[1], [], $user) ? "{{$matches[1]}}" : '';
        }, $buttons);
    }

    /**
     * Use to invalidate cache.
     */
    public static function invalidate()
    {
        self::$_userRoutes = [];
        self::$_defaultRoutes = null;
        self::$_routes = null;
        if (Configs::cache() !== null) {
            TagDependency::invalidate(Configs::cache(), Configs::CACHE_TAG);
        }
    }

    /**
     * Normalizes a user-supplied value for safe interpolation into a SINGLE
     * log line: every run of whitespace and control characters (newlines/CRs
     * included, plus Unicode line/paragraph separators U+2028/U+2029 and
     * escape/other C0 controls) is collapsed to one space and the result is
     * trimmed. A crafted username/email/IP with embedded newlines could
     * otherwise forge extra log rows / corrupt log parsing (audit QA wave-22
     * F22-2 — used by the login-lockout and password-reset log statements).
     *
     * @param mixed $value the raw user-supplied value.
     * @return string single-line, whitespace-collapsed string — never null
     * and NEVER the raw input: even invalid UTF-8 (where the /u pattern
     * cannot run) is re-encoded/byte-scrubbed so no CR/LF can survive.
     */
    public static function sanitizeForLog($value)
    {
        $s = (string) $value;

        // Fast path (valid UTF-8, plain ASCII included): collapse every
        // whitespace / control / format / line-or-paragraph-separator run to
        // one space and trim. Neutralizes CRLF, lone CR/LF, ANSI escapes and
        // U+2028/U+2029.
        $clean = preg_replace('/[\s\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', ' ', $s);
        if ($clean !== null) {
            return trim($clean);
        }

        // Invalid UTF-8: the /u pattern refuses to run. The old code then fell
        // back to the RAW bytes, so a crafted "\xFF\r\nFORGED" smuggled CRLF
        // into the log line (CWE-117 log injection, audit QA wave-23 F23-1).
        // Re-encode with iconv //IGNORE (drops the malformed \xFF bytes) and
        // run the same collapse — clean for every realistic payload.
        $utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($utf8 !== false) {
            $clean = preg_replace('/[\s\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', ' ', $utf8);
            if ($clean !== null) {
                return trim($clean);
            }
        }

        // Last resort (iconv missing/undecodable): manual byte pass that keeps
        // only printable ASCII and turns every other byte (C0 controls, DEL,
        // C1 controls, stray high bytes) into a space. The output is
        // guaranteed single-line ASCII — never a raw control byte.
        $out = '';
        $pendingSpace = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($s[$i]);
            if ($ord >= 0x20 && $ord <= 0x7E) {
                if ($pendingSpace) {
                    $out .= ' ';
                    $pendingSpace = false;
                }
                $out .= $s[$i];
            } else {
                $pendingSpace = true;
            }
        }

        return trim($out);
    }
}
