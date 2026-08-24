<?php
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/vendor/yiisoft/yii2/Yii.php';
defined('YII_TEST_ENTRY_FILE') || define('YII_TEST_ENTRY_FILE', dirname(__DIR__, 2) . '/web/index-test.php');
defined('YII_TEST_ENTRY_URL') || define('YII_TEST_ENTRY_URL', '/index-test.php');
$config = require dirname(__DIR__) . '/config/functional.php';
unset($config['components']['mailer']);
new yii\web\Application($config);
