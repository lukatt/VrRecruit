<?php

namespace Vreasy\Zend\Plugins;

use Vreasy\Exceptions\FatalErrorException;

final class ErrorHandler extends \Zend_Controller_Plugin_ErrorHandler
{
    public function __construct($options = [])
    {
        parent::__construct($options);
        register_shutdown_function(get_called_class().'::handleFatal');
    }

    static public function handleFatal()
    {
        $lastError = error_get_last();
        // Lets handle fatal errors with a proper HTTP status code
        if ($lastError && E_ERROR == @$lastError['type']) {
            if (!headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
            }
        }
    }
}
