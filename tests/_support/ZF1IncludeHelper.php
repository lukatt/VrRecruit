<?php

namespace Codeception\Module;

use Codeception\TestCase;

class ZF1IncludeHelper extends \Codeception\Module
{
    protected $config = ['env' => 'test'];
    protected $cleanup = false;

    public function _initialize()
    {
        $this->cleanup = @$this->config['cleanup'] ?: false;
        defined('APPLICATION_ENV') || define('APPLICATION_ENV', $this->config['env']);
        $cliIndex = implode(
            DIRECTORY_SEPARATOR,
            ['vreasy', 'application', 'cli', 'cliindex.php']
        );
        require_once(\Codeception\Configuration::projectDir() . $cliIndex);
        $module = $this->hasModule('DbzHelper')
            ? $this->getModule('DbzHelper')
            : $this->getModule('MysqlHelper');

        $db = \Zend_Registry::get('Zend_Db');
        $dbReflection = new \ReflectionObject($db);
        $_connection = $dbReflection->getProperty('_connection');
        $_connection->setAccessible(true);
        if (($pdoConnection = $_connection->getValue($db)) && $module->dbh !== $pdoConnection) {
            $module->dbh = null;
            $module->dbh = $pdoConnection;
            $moduleDriverReflection = new \ReflectionObject($module->driver);
            $dbh = $moduleDriverReflection->getProperty('dbh');
            $dbh->setAccessible(true);
            $dbh->setValue($module->driver, $pdoConnection);
        }
//
//        if ($db = \Zend_Registry::get('Zend_Db')) {
//            $profiler = $db->getProfiler();
//            $queries = $profiler->getTotalNumQueries() - $this->queries;
//            $time = $profiler->getTotalElapsedSecs() - $this->time;
//            $this->debugSection('Db',$queries.' queries');
//            $this->debugSection('Time',round($time,2).' secs taken');
//            $this->time = $profiler->getTotalElapsedSecs();
//            $this->queries = $profiler->getTotalNumQueries();
//        }
    }

    public function _beforeSuite($settings = array())
    {
        $module = $this->hasModule('DbzHelper')
            ? $this->getModule('DbzHelper')
            : $this->getModule('MysqlHelper');
        $db = \Zend_Registry::get('Zend_Db');
        $dbReflection = new \ReflectionObject($db);
        $_connection = $dbReflection->getProperty('_connection');
        $_connection->setAccessible(true);
        if (($pdoConnection = $_connection->getValue($db)) && $module->dbh !== $pdoConnection) {
            $module->dbh = null;
            $module->dbh = $pdoConnection;
            $moduleDriverReflection = new \ReflectionObject($module->driver);
            $dbh = $moduleDriverReflection->getProperty('dbh');
            $dbh->setAccessible(true);
            $dbh->setValue($module->driver, $pdoConnection);
        }
    }

    public function _before(TestCase $test)
    {
        if ($this->cleanup) {
            \Zend_Registry::get('Zend_Db')->beginTransaction();
        }
    }

    public function _after(TestCase $test)
    {
        try {
            if ($this->cleanup) {
                \Zend_Registry::get('Zend_Db')->rollback();
            }
        } catch (\Exception $e) {
            if ($e->getMessage() == \Magento\Framework\DB\Adapter\AdapterInterface::ERROR_ASYMMETRIC_ROLLBACK_MESSAGE) {
                // Catch exception that means that the DB query failed!
                // This usually happens because something else failed in the query.
                // eg. "Integrity constraint violation"
                // To debug this sometimes is easier to switch back
                // to the regular db adapter in the db.php instead of using the magento.
                // Also remember to turn cleanup=true in the *.suite.yml
            } else {
                throw $e;
            }
        }
    }

}
