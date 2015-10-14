<?php

use Doctrine\Common\Inflector\Inflector;

class Api_Controller_Action_Helper_Serializer extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * Enables or disables the rendering of a json root object
     * @var boolean
     */
    public $jsonRootObjectEnabled = false;

    /**
     * Enables or disables forcing all json properties to be snake cased
     * @var boolean
     */
    public $forceJsonPropertiesSnakeCase = false;

    /**
     * Enables or disables the automatic rendering provided by the serializer
     * @var boolean
     */
    public $renderEnabled = true;

    public function enableJson($opts = [])
    {
        $jsonRootObjectEnabled = false;
        $init = true;
        extract($opts, EXTR_IF_EXISTS);

        $this->jsonRootObjectEnabled = !!$jsonRootObjectEnabled;

        $contextSwitch = $this->getActionController()->getHelper('contextSwitch');
        $contextSwitch->setDefaultContext('json');
        $contextSwitch->setCallbacks(
            'json',
            [
                'TRIGGER_INIT' => [$this, 'initJsonContext'],
                'TRIGGER_POST' => [$this, 'postJsonContext']
            ]
        );
        $contextSwitch->setActionContext($this->getRequest()->getActionName(), 'json');
        if ($init) {
            $this->getActionController()->getHelper('contextSwitch')->initContext('json');
        }
        return $this;
    }

    public function initJsonContext()
    {
        $this->getRequest()->setParam('setVreasyJson', true);
        if (!$this->getActionController()->getHelper('contextSwitch')->getAutoJsonSerialization()) {
            return;
        }
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $view = $viewRenderer->view;
        if ($view instanceof Zend_View_Interface) {
            $viewRenderer->setNoRender(true);
        }
    }

    /**
     * When json autoserialization is On, it serializes the view variables as json objects
     */
    public function postJsonContext()
    {
        if (!$this->getActionController()->getHelper('contextSwitch')->getAutoJsonSerialization()) {
            return;
        }

        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $view = $viewRenderer->view;
        if ($view instanceof Zend_View_Interface && $this->renderEnabled) {
            /**
             * @see \Zend_Json
             */
            if(method_exists($view, 'getVars')) {
                require_once 'Zend/Json.php';
                $vars = array_filter($view->getVars());
                $json = '';

                // When only one variable is set serialize as a single object with no "root key"
                if(($values = array_values($vars)) && 1 == count($values) && !$this->jsonRootObjectEnabled) {
                    $json = Zend_Json::encode(array_pop($values));
                } else {
                    $json = Zend_Json::encode($vars);
                }

                if ($this->forceJsonPropertiesSnakeCase) {
                    $json = preg_replace_callback(
                        '/(?<=[\{\,])\"([^\"]+)[\"]/',
                        function($m) {
                            return '"'.Inflector::tableize($m[1]).'"';
                        },
                        $json
                    );
                }

                $this->getResponse()->setBody($json);
            } else {
                require_once 'Zend/Controller/Action/Exception.php';
                throw new Zend_Controller_Action_Exception('View does not implement the getVars() method needed to encode the view into JSON');
            }
        }
    }

    public function setNoRender($noRender)
    {
        $this->renderEnabled = !$noRender;
        return $this;
    }
}
