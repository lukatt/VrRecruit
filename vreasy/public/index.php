<?php

date_default_timezone_set('Europe/Madrid');

// Define path to application directory
defined('APPLICATION_PATH')
|| define('APPLICATION_PATH', realpath(__DIR__.'/../application'));

// Define application environment
defined('APPLICATION_ENV')
|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

defined('LIBRARY_PATH')
|| define('LIBRARY_PATH', realpath(__DIR__.'/../../library'));

# Composer
require implode(
    DIRECTORY_SEPARATOR,
    [realpath(APPLICATION_PATH . '/../../vendor'), 'autoload.php']
);

// Ensure library/ is on include_path
set_include_path(implode(
    PATH_SEPARATOR,
    [LIBRARY_PATH, get_include_path()]
));

# Zend_Application
require_once 'Zend/Application.php';
require_once 'Vreasy/Utils/Functions.php';

if (php_sapi_name() != 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
    // Create application, bootstrap, and run
    $application = new \Zend_Application(
        APPLICATION_ENV,
        APPLICATION_PATH . '/configs/application.php'
    );

    $application->bootstrap()->run();
}
