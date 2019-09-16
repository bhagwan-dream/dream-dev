<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'api\controllers',
    'bootstrap' => ['log'],
    'modules' => [
        'v1' => [
            'class' => 'api\modules\v1\module',
        ],
        'v2' => [
            'class' => 'api\modules\v2\module',
        ],
    ],
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-backend',
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-backend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'advanced-backend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [ 
                'v1/<controller:[\w-]+>/<action:[\w---]+>/<id:\d+>' => 'v1/<controller>/<action>',
                'v1/<module:\w+>/<controller:[\w-]+>/<action:[\w---]+>/<id:\d+>' => 'v1/<module>/<controller>/<action>',
                'v2/<controller:[\w-]+>/<action:[\w---]+>/<id:\d+>' => 'v2/<controller>/<action>',
                'v2/<module:\w+>/<controller:[\w-]+>/<action:[\w---]+>/<id:\d+>' => 'v2/<module>/<controller>/<action>',
            ],
        ],
        
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
        ],
       
    ],
    'params' => $params,
];
