<?php
$production = [
    'constants' => [
        'APP_VERSION' => '0.3',
        'DATE_FORMAT' => 'Y-m-d H:i:s',
        'DATESHORT_FORMAT' => 'Y-m-d',
        'DEFAULT_TIMEZONE' => 'Europe/Madrid',
        'APP_TITLE' => 'Vreasy &trade; Devs - Task confirmation',
    ],
    'phpSettings' =>  [
        'display_startup_errors' => '0',
        'display_errors' => '0',
    ],
    'includePaths' => [
        'library' => APPLICATION_PATH . '/../../library',
    ],
    'bootstrap' => [
        'path' => APPLICATION_PATH . '/Bootstrap.php',
        'class' => 'Bootstrap',
    ],
    'appnamespace' => 'Application',
    'resources' => [
        'frontController' => [
            'controllerDirectory' => APPLICATION_PATH . '/controllers',
            'params' => [
                'displayExceptions' => '0',
            ],
            'moduleDirectory' => APPLICATION_PATH . '/modules',
        ],
        'layout' => [
            'layoutPath' => APPLICATION_PATH . '/layouts/scripts',
        ],
        'modules' => [''],
        'view' => [''],
        'locale' => [
            'default' => 'en_US'
        ]
    ],
    'convert' => [
        'path' => 'convert',
    ],
    'smtp' => [
        // TODO: Configure postfix to avoid leaking this credentials
        'auth' => 'login',
        'host' => 'localhost',
        'username' => 'no-reply@vreasy.com',
        'password' => '123qwerty!',
        'ssl' => 'ssl',
    ],
];

$test = [
    'resources' => [
        'frontController' => [
            'params' => [
                'displayExceptions' => '0',
            ],
            'throwexceptions' => '0',
        ],
    ],
    'constants' => [
        'HTTP_HOST' => 'localhost',
        'HOST' => 'localhost',
    ],
];

$development = [
    'resources' => [
        'frontController' => [
            'params' => [
                'displayExceptions' => '1',
            ],
            'throwexceptions' => '0',
        ],
    ],
    'phpSettings' => [
        'display_startup_errors' => '1',
        'display_errors' => '1',
    ],
    'constants' => [
        'HTTP_HOST' => 'www.vreasy.dev',
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
