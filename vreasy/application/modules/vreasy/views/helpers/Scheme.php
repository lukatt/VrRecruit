<?php

class Vreasy_Helper_Scheme extends Zend_View_Helper_Abstract
{

    static private $scheme;

    public function scheme()
    {
        if (!self::$scheme) {
            self::$scheme = current_http_scheme();
        }
        return self::$scheme;
    }
}
