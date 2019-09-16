<?php
return [
    'components' => [

        // Live Connection

        /*'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=dream-exch-group.cluster-ctmwzely1cbh.eu-west-1.rds.amazonaws.com;dbname=dream_exch',
            'username' => 'dream_exch',//root
            'password' => 'atqjbb9fQxpy3yV9',//blueDimond@123
            'charset' => 'utf8',
            'attributes' => [
                // use a smaller connection timeout
                PDO::ATTR_TIMEOUT => 15,
            ],
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],
        'db1' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=live-readers.cluster-custom-ctmwzely1cbh.eu-west-1.rds.amazonaws.com;dbname=dream_exch',
            'username' => 'dream_exch',//root
            'password' => 'atqjbb9fQxpy3yV9',//blueDimond@123
            'charset' => 'utf8',
            'attributes' => [
                // use a smaller connection timeout
                PDO::ATTR_TIMEOUT => 15,
            ],
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],
        'db2' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=live-readers.cluster-custom-ctmwzely1cbh.eu-west-1.rds.amazonaws.com;dbname=dream_exch',
            'username' => 'dream_exch',//root
            'password' => 'atqjbb9fQxpy3yV9',//blueDimond@123
            'charset' => 'utf8',
            'attributes' => [
                // use a smaller connection timeout
                PDO::ATTR_TIMEOUT => 15,
            ],
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],
        'db3' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=live-readers.cluster-custom-ctmwzely1cbh.eu-west-1.rds.amazonaws.com;dbname=dream_exch',
            'username' => 'dream_exch',//root
            'password' => 'atqjbb9fQxpy3yV9',//blueDimond@123
            'charset' => 'utf8',
            'attributes' => [
                // use a smaller connection timeout
                PDO::ATTR_TIMEOUT => 15,
            ],
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],*/

// Dev Server Connection

        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=dream-exch-dev.cluster-ctmwzely1cbh.eu-west-1.rds.amazonaws.com;dbname=dream_exch',
            'username' => 'dream_exch',//root
            'password' => 'atqjbb9fQxpy3yV9',//blueDimond@123
            'charset' => 'utf8',
            'charset' => 'utf8',
            'attributes' => [
                // use a smaller connection timeout
                PDO::ATTR_TIMEOUT => 15,
            ],
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],
        'db1' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=dream-exch-dev.cluster-ro-ctmwzely1cbh.eu-west-1.rds.amazonaws.com;dbname=dream_exch',
            'username' => 'dream_exch',//root
            'password' => 'atqjbb9fQxpy3yV9',//blueDimond@123
            'charset' => 'utf8',
            'charset' => 'utf8',
            'attributes' => [
                // use a smaller connection timeout
                PDO::ATTR_TIMEOUT => 15,
            ],
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],
        'db2' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=dream-exch-dev.cluster-ro-ctmwzely1cbh.eu-west-1.rds.amazonaws.com;dbname=dream_exch',
            'username' => 'dream_exch',//root
            'password' => 'atqjbb9fQxpy3yV9',//blueDimond@123
            'charset' => 'utf8',
            'charset' => 'utf8',
            'attributes' => [
                // use a smaller connection timeout
                PDO::ATTR_TIMEOUT => 15,
            ],
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],
        'db3' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=dream-exch-dev.cluster-ro-ctmwzely1cbh.eu-west-1.rds.amazonaws.com;dbname=dream_exch',
            'username' => 'dream_exch',//root
            'password' => 'atqjbb9fQxpy3yV9',//blueDimond@123
            'charset' => 'utf8',
            'charset' => 'utf8',
            'attributes' => [
                // use a smaller connection timeout
                PDO::ATTR_TIMEOUT => 15,
            ],
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],

        // Local Connection
        /*'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=localhost;dbname=dream_exch',
            'username' => 'root',
            'password' => 'admin#123',
            'charset' => 'utf8',

        ],
        'db1' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=localhost;dbname=dream_exch',
            'username' => 'root',
            'password' => 'admin#123',
            'charset' => 'utf8',

        ],
        'db2' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=localhost;dbname=dream_exch',
            'username' => 'root',
            'password' => 'admin#123',
            'charset' => 'utf8',

        ],
        'db3' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=localhost;dbname=dream_exch',
            'username' => 'root',
            'password' => 'admin#123',
            'charset' => 'utf8',

        ], */

        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@common/mail',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
    ],
];
