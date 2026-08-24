<?php
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/vendor/yiisoft/yii2/Yii.php';
$config = require dirname(__DIR__) . '/config/acceptance.php';
unset($config['components']['mailer']);
new yii\web\Application($config);
