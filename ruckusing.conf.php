<?php

//----------------------------
// DATABASE CONFIGURATION
//----------------------------

set_include_path(implode(
    PATH_SEPARATOR,
    array(realpath(__DIR__ . '/library'), get_include_path())
));

function getRuckusingEnv($argv)
{
    $num_args = count($argv);

    $options = array();
    for ($i = 0; $i < $num_args; $i++) {
        $arg = $argv[$i];
        if (stripos($arg, '=') !== false) {
            list($key, $value) = explode('=', $arg);
            // Allow both upper and lower case parameters
            $key = strtolower($key);
            $options[$key] = $value;
            if ($key == 'env') {
                return $value;
            }
        }
    }
}

if ($env = getRuckusingEnv($argv)) {
    putenv('APPLICATION_ENV='.$env);
}

defined('APPLICATION_ENV') || define(
    'APPLICATION_ENV',
    (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production')
);


require_once 'Zend/Config.php';
$config = new Zend_Config( require realpath(__DIR__ . '/vreasy/application/configs/db.php'));

$environments = [APPLICATION_ENV];

foreach ($environments as $env) {
    $environments[$env] = [
        'directory' => 'webapp',
        'type' => 'mysql',
        'charset' => 'utf8',
        'host' => $config->database->params->host,
        'port' => 3306,
        'database' => $config->database->params->dbname,
        'user' => $config->database->params->username,
        'password' => $config->database->params->password,
    ];
}

$db_config = [
    'db' => $environments,
    'migrations_dir' => RUCKUSING_WORKING_BASE . DIRECTORY_SEPARATOR . 'migrations',
    'db_dir' => RUCKUSING_WORKING_BASE . DIRECTORY_SEPARATOR . 'db',
    'log_dir' => RUCKUSING_WORKING_BASE . DIRECTORY_SEPARATOR . 'logs',
    'ruckusing_base' => implode(DIRECTORY_SEPARATOR, ['vendor', 'ruckusing','ruckusing-migrations'])
];

return $db_config;
