<?php

require_once dirname(__DIR__, 3) . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@tests', dirname(__DIR__, 2));
$config = require Yii::getAlias('@tests/codeception/config/unit.php');
unset($config['components']['mailer']);
new yii\web\Application($config);
