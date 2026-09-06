<?php
/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'mdm-admin-test',
    'basePath' => dirname(dirname(__DIR__)), // @tests
    'vendorPath' => dirname(dirname(dirname(__DIR__))) . '/vendor',
    'language' => 'en-US',
    'aliases' => [
        '@mdm/admin' => dirname(dirname(dirname(__DIR__))),
    ],
    // Test-only tuning of the mdm\admin Configs singleton. `strict => false`
    // lets Helper::filter()/checkRoute() work with a plain user-id argument
    // (non-strict menu-filtering path) instead of a yii\web\User object, which
    // keeps the menu-filter unit test independent of the web user component.
    'params' => [
        'mdm.admin.configs' => [
            'strict' => false,
        ],
    ],
    'modules' => [
        'admin' => [
            'class' => 'mdm\\admin\\Module',
        ]
    ],
    'components' => [
        'db' => require(__DIR__ . '/db.php'),
        'mailer' => [
            'useFileTransport' => true,
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        'authManager' => [
            'class' => 'yii\\rbac\\DbManager'
        ],
        'cache' => [
            'class' => 'yii\\caching\\DummyCache',
        ],
        'i18n' => [
            'translations' => [
                'rbac-admin' => [
                    'class' => 'yii\\i18n\\PhpMessageSource',
                    'sourceLanguage' => 'en',
                    'basePath' => '@mdm/admin/messages'
                ]
            ]
        ]
    ],
];
