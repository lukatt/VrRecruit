<?php

namespace Vreasy\Exceptions;

use Exception;

class ResourceNotFoundException extends Exception
{
    const ERROR_MESSAGE = 'exceptionResourceNotFound';
    
    public function __construct($message = null)
    {
        if(!$message){
            $message = self::ERROR_MESSAGE;
        }

        $this->message = $message;
    }
}