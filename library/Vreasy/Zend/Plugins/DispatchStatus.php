<?php

namespace Vreasy\Zend\Plugins;

final class DispatchStatus extends \Zend_Controller_Plugin_Abstract
{
    const ROUTE_STARTINGUP = 'routeStartingUp';
    const ROUTE_SHUTINGDOWN = 'routeShutingDown';
    const DISPATCHLOOP_STARTINGUP = 'dispatchLoopStartingUp';
    const PRE_DISPATCHING = 'preDispatching';
    const POST_DISPATCHING = 'postDispatching';
    const DISPATCHLOOP_SHUTINGDOWN = 'dispatchLoopShutingDown';

    protected $validStates = [
        self::ROUTE_STARTINGUP,
        self::ROUTE_SHUTINGDOWN,
        self::DISPATCHLOOP_STARTINGUP,
        self::PRE_DISPATCHING,
        self::POST_DISPATCHING,
        self::DISPATCHLOOP_SHUTINGDOWN,
    ];
    protected $currentState;


    public function routeStartup(\Zend_Controller_Request_Abstract $request)
    {
        $this->currentState = self::ROUTE_STARTINGUP;
    }

    public function routeShutdown(\Zend_Controller_Request_Abstract $request)
    {
        $this->currentState = self::ROUTE_SHUTINGDOWN;
    }

    public function dispatchLoopStartup(\Zend_Controller_Request_Abstract $request)
    {
        $this->currentState = self::DISPATCHLOOP_STARTINGUP;
    }

    public function preDispatch(\Zend_Controller_Request_Abstract $request)
    {
        $this->currentState = self::PRE_DISPATCHING;
    }

    public function postDispatch(\Zend_Controller_Request_Abstract $request)
    {
        $this->currentState = self::POST_DISPATCHING;
    }

    public function dispatchLoopShutdown()
    {
        $this->currentState = self::DISPATCHLOOP_SHUTINGDOWN;
    }

    public function isDispatching()
    {
        return in_array($this->currentState, $this->validStates);
    }
}
