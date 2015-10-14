<?php

namespace Vreasy\Zend\Plugins;

final class PostProcess extends \Zend_Controller_Plugin_Abstract
{
    protected $callables = [];
    protected $setup = [];
    protected $obLevel;

    public function routeStartup(\Zend_Controller_Request_Abstract $request)
    {
        $this->obLevel = ob_get_level();
        register_shutdown_function($this->getDispatchLoopShutdownClosure());
    }

    public function getDispatchLoopShutdownClosure()
    {
        $closure = function() {
            return $this->dispatchLoopShutdown();
        };
        $closure = $closure->bindTo($this);
        return $closure;
    }

    public function dispatchLoopShutdown()
    {
        try {
            $this->callables = \Zend_Registry::get('PostProcess');
        } catch (\Zend_Exception $e) {
            // No post processing callables found
            $this->callables = [];
        }

        if ($this->callables) {
            set_time_limit(0);
            ignore_user_abort(true);

            // Response will be handled by this
            \Zend_Controller_Front::getInstance()->returnResponse(true);
            $response = $this->getResponse();
            $request = $this->getRequest();
            if ($response->isException()) {
                return;
            }

            // Catch any not buffered content (eg. die)
            ob_start();
            $curObLevel = ob_get_level();
            if ($curObLevel > $this->obLevel) {
                do {
                    ob_end_clean();
                    $curObLevel = ob_get_level();
                } while ($curObLevel > $this->obLevel);
            }
            $response->appendBody(ob_get_contents());
            // Clean any out of sync ob
            while (@ob_end_clean());

            if (false === stripos($request->getHeader('Connection'), 'keep-alive')) {
                $response->setHeader('Connection', 'close');
            }

            ob_start();
            // echoing the body
            $response->outputBody();
            $size = ob_get_length();
            try {
                $response->setHeader('Content-length', $size, true);
                $response->sendHeaders();
            } catch(\Zend_Controller_Response_Exception $e) {
                // Do nothing.
                // This means that some ugly guy did sendResponse already
            }
            ob_end_flush();
            flush();

            // Clean any out of sync ob
            while (@ob_end_clean());
            // Reset the response content once flushed. So if it's called again
            // as part of the shutdown callback, it won't output the same twice.
            $response->clearBody();

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $this->process();
        }
    }

    private function process()
    {
        // Capture any output
        ob_start();
        foreach ($this->callables as $idx => $func) {
            unset($this->callables[$idx]);
            if (!is_array($func)) {
                call_user_func($func);
            } else {
                list($funcToCall, $params) = $func;
                call_user_func_array($funcToCall, $params);
            }
        }
        while (@ob_end_clean());
    }
}
