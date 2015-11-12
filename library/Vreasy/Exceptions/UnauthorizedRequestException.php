<?php

namespace Vreasy\Exceptions;

use Exception;

class UnauthorizedRequestException extends Exception
{
    const ERROR_MESSAGE = 'exceptionUnauthorizedRequest';

    public function __construct($message = null)
    {
        if(!$message){
            $message = self::ERROR_MESSAGE;
        }

        $this->message = $message;
    }
}