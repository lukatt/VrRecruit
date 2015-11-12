<?php

class Api_Controller_Action_Helper_Sanitizer extends Zend_Controller_Action_Helper_Abstract
{
    public function cleanUrl($url)
    {
        if (0 !== strripos($url, 'http')) {
            // Because it does not starts with http, prepend the protocol to it.
            $url = 'http://'.$url;
        }

        // Now lets validate the url and sanitize it when valid
        return filter_var($url, FILTER_VALIDATE_URL)
            ? filter_var($url, FILTER_SANITIZE_URL)
            : null;
    }
}
