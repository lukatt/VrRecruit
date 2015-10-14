<?php
require_once( dirname (dirname ( __DIR__ )) . '/public/index.php' );
// Create application, bootstrap, and run
$application = null;
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.php'
);
$application->bootstrap('Autoload');
$application->bootstrap('Db');
$application->bootstrap('Log');
$application->bootstrap('Config');
$application->bootstrap('Valitron');
$application->bootstrap('UriLabelExpander');
$application->bootstrap('View');
$application->bootstrap('GuzzleClient');
