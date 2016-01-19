<?php
namespace Codeception\Module;

use Codeception\TestCase;
use Vreasy\Models\Task;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class FunctionalHelper extends \Codeception\Lib\InnerBrowser
{
    public function __construct($config = null)
    {
        parent::__construct($config);
    }

    function _before(TestCase $test)
    {
        $GLOBALS['isDebugging'] = null;
        $this->client = $this->getModule('ZF1')->client;
        $crawler = new \ReflectionProperty(get_class($this->client), 'crawler');
        $crawler->setAccessible(true);
        $this->crawler = $crawler->getValue($this->client);
    }

    public function enableDebugging()
    {
        $GLOBALS['isDebugging'] = 'isDebugging';
    }

    function _after(TestCase $test)
    {
        $GLOBALS['isDebugging'] = null;
        unset($GLOBALS['isDebugging']);
    }

    public function seeEquals($value, $test, $message = '')
    {
        \PHPUnit_Framework_Assert::assertEquals($value, $test, $message);
    }

    public function haveTask($params = [])
    {
    	$I = $this->getModule('DbzHelper');
    	$params = array_merge(
    			[
    					'deadline' => gmdate(DATE_FORMAT),
    					'assigned_phone' => '+34666666666',
    					'assigned_name' => 'John Doe',
                        'status' => 'pending',
    			],
    			$params
    	);
    	if (isset($params['id'])) {
    		$task = Task::findOrInit($params['id']);
    		$task = Task::hydrate($task, $params);
    	} else {
    		$task = Task::instanceWith($params);
    	}
    	\PHPUnit_Framework_Assert::assertTrue((bool) $task->save());
    	return $task;
    }

}
