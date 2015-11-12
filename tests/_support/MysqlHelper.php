<?php

namespace Codeception\Module;

use Codeception\Lib\Driver\Db as Driver;
use Codeception\Exception\Module as ModuleException;
use Codeception\Exception\ModuleConfig as ModuleConfigException;
use Codeception\Configuration as Configuration;

class MysqlHelper extends \Codeception\Module\Db
{
    protected $mysqlBin = 'mysql';

    public function _initialize()
    {
        if ($this->config['dump'] && ($this->config['cleanup'] || ($this->config['populate']))) {

            if (!file_exists(Configuration::projectDir() . $this->config['dump'])) {
                throw new ModuleConfigException(
                    __CLASS__,
                    "\nFile with dump doesn't exist.
                    Please, check path for sql file: " . $this->config['dump']
                );
            }
            // $sql = file_get_contents(Configuration::projectDir() . $this->config['dump']);
            // $sql = preg_replace('%/\*(?!!\d+)(?:(?!\*/).)*\*/%s', "", $sql);
            $this->sql = Configuration::projectDir() . $this->config['dump'];
        }

        try {
            $this->driver = Driver::create($this->config['dsn'], $this->config['user'], $this->config['password']);
        } catch (\PDOException $e) {
            throw new ModuleException(__CLASS__, $e->getMessage() . ' while creating PDO connection');
        }

        $this->dbh = $this->driver->getDbh();

        // starting with loading dump
        if ($this->config['populate']) {
            $this->cleanup();
            $this->loadDump();
            $this->populated = true;
        }
        $this->mysqlBin = @$this->config['mysqlBin'] ?: $this->mysqlBin;
    }


    protected function loadDump()
    {
        if (! $this->sql) {
            return;
        }
        try {
            $output = [];
            $return = 0;
            $dsn = [];
            foreach (explode(';', str_replace('mysql:', '', $this->config['dsn'])) as $item) {
                $i = explode('=', $item);
                $dsn[$i[0]] = $i[1];
            }
            $user = '-u '.$this->config['user'];
            $password = $this->config['password'] ? '-p '.$this->config['password'] : '';
            $host = '-h '.(@$dsn['host'] ?: '127.0.0.1');
            $port = '-P '.(@$dsn['port'] ?: 3306);
            $database = '-D '.$dsn['dbname'];

            exec(
                implode(
                    ' ',
                    [
                        $this->mysqlBin, $user, $password, $host, $port, $database, '<', $this->sql
                    ]
                ),
                $output,
                $return
            );
            if (0 !== $return) {
                throw new \Exception("Error importing mysql dump: ".implode("\n", $output));
            }

        } catch (\Exception $e) {
            throw new ModuleException(
                __CLASS__,
                $e->getMessage()
            );
        }
    }
}
