<?php

use Valitron\Validator;
use Vreasy\UriLabelExpander;
use Vreasy\FeatureToggle\FeatureConfig;
use Vreasy\Zend\VreasyView;
use Vreasy\NullObject;

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    public function _initAutoload()
    {
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->registerNamespace('Vreasy');
    }

    protected function setconstants($constants)
    {
        foreach ($constants as $key=>$value) {
            if(!defined($key)){
                define($key, $value);
            }
        }
    }

    public function _initActionHelpers()
    {
        Zend_Controller_Action_HelperBroker::addPath(
            APPLICATION_PATH .'/modules/vreasy/controllers/helpers',
            'Vreasy_Controller_Action_Helper'
        );
        Zend_Controller_Action_HelperBroker::addPath(
            APPLICATION_PATH .'/modules/api/controllers/helpers',
            'Api_Controller_Action_Helper'
        );
    }

    public function _initRouter()
    {
        $viewsRoute = new Vreasy_Rest_Route(
            Zend_Controller_Front::getInstance(),
            ['module' => 'vreasy'],
            ['vreasy' => ['task','twilio',]
        ]);
        Zend_Controller_Front::getInstance()->getRouter()->addRoute(
            'vreasy',
            $viewsRoute
        );

        $restRoute = new Zend_Rest_Route(
            Zend_Controller_Front::getInstance(),
            [],
            ['api']
        );
        Zend_Controller_Front::getInstance()->getRouter()->addRoute(
            'vreasy',
            $restRoute
        );

        Zend_Controller_Front::getInstance()->addModuleDirectory(
            APPLICATION_PATH.'/modules'
        );
    }

    public function _initDb()
    {
        if (\Zend_Registry::isRegistered('Zend_Db')) {
            return \Zend_Registry::get('Zend_Db');
        } else {
            $dbconf = new \Zend_Config( require APPLICATION_PATH . '/configs/db.php');
            \Zend_Registry::set('Zend_Db', Zend_Db::factory($dbconf->database));
            \Zend_Registry::get('Zend_Db')->setFetchMode(Zend_Db::FETCH_OBJ);
            \Zend_Db_Table::setDefaultAdapter(Zend_Registry::get('Zend_Db'));
            return \Zend_Registry::get('Zend_Db');
        }
    }

    protected function _initLog()
    {
        $writer = new Zend_Log_Writer_Stream('php://stderr');
        $logger = new Zend_Log($writer);
        Zend_Registry::set('Zend_Log', $logger);
        // For all your syslog needs
        openlog("vreasy", LOG_PID|LOG_PERROR|LOG_ODELAY, LOG_LOCAL0);
    }

    protected function _initConfig()
    {
        $config = new Zend_Config( require APPLICATION_PATH . '/configs/application.php');
        Zend_Registry::set('config', $config);
    }

    protected function _initView()
    {
        $view = new VreasyView();
        $view->addHelperPath(
            APPLICATION_PATH . '/modules/vreasy/views/helpers',
            'Vreasy_Helper');
        $view->addScriptPath(APPLICATION_PATH . '/modules/vreasy/views/scripts');
        $view->addScriptPath(APPLICATION_PATH .'/modules/vreasy/views/helpers');
        $view->addFilterPath(
            APPLICATION_PATH . '/modules/vreasy/views/filters',
            'Vreasy_View_Filter_');
        $view->addFilter('ScriptVersioning');
        $view->addFilter('CssVersioning');

        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
        $viewRenderer->setView($view);
        \Zend_Registry::set('VreasyView', $view);
        return $view;
    }

    protected function _initValitron()
    {
        Validator::addRule(
            'custom',
            function($field, $value, array $params) {
                return array_reduce(
                    $params,
                    function($v, $c) use($field, $value) {
                        if (!$v && $v !== false) {
                            // Init $v
                            $v = call_user_func($c, $field, $value);
                        } else {
                            $v = $v && call_user_func($c, $field, $value);
                        }
                        unset($c);
                        return $v;
                    }
                );
            }
        );
    }

    protected function _initUriLabelExpander()
    {
        UriLabelExpander::setLabels(['or', 'and']);
    }

    protected function _initGuzzleClient()
    {
        $client = new \Guzzle\Http\Client();
        // Uncomment the following lines to configure a proxy
        // and record http interactions for use in the testing suite
        // $client->setConfig([
        //     'curl.options' => [
        //         'CURLOPT_SSL_VERIFYHOST' => 0,
        //         'CURLOPT_SSL_VERIFYPEER' => 0,
        //     ],
        //     'request.options' => array(
        //         'proxy'   => 'http://localhost:8080',
        //     )
        // ]);

        if (false !== stripos(APPLICATION_ENV, 'circle')) {
            // Avoid calling external services when running the testing suite
            $mock = new Vreasy\Tests\MockAllRequestsPlugin();
            $mock->addResponse(new \Guzzle\Http\Message\Response(200));
            $client->addSubscriber($mock);
        }
        Zend_Registry::set('GuzzleClient', $client);
    }

}
