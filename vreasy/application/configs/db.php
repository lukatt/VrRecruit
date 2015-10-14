<?php
$production = [
    'database' => [
        'adapter' => 'PDO_MYSQL',
        'params' => [
            'charset' => 'utf8',
        ],
    ],
];

$development = [
    'database' => [
        'adapter' => 'PDO_MYSQL',
        'params' => [
            'dbname' => getenv('PHP_DB_DBNAME') ?: 'vreasy_task_confirmation',
            'host' => getenv('PHP_DB_HOST') ?: '127.0.0.1',
            'username' => getenv('PHP_DB_USERNAME') ?: 'vreasy',
            'password' => getenv('PHP_DB_PASSWORD') ?: 'FeA336101-vreasy_task_confirmation',
            'charset' => 'utf8'
        ],
    ],
];

$test = [
    'database' => [
        'adapter' => 'Magentopdomysql',
        'params' => [
            'adapterNamespace' => 'Vreasy_ZendDbAdapters',
            'dbname' => getenv('PHP_DB_DBNAME') ?: 'vreasy_task_confirmation_test',
            'host' => getenv('PHP_DB_HOST') ?: '127.0.0.1',
            'username' => getenv('PHP_DB_USERNAME') ?: 'ubuntu',
            'password' => getenv('PHP_DB_PASSWORD') ?: '',
            'charset' => 'utf8',
        ],
    ],
];

$test = array_replace_recursive($production, $test);
$development = array_replace_recursive($production, $development);
$config = array_merge_recursive(
    ['production' => $production],
    ['test' => $test],
    ['development' => $development]
);
return $config[APPLICATION_ENV];

