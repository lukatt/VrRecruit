<?php

use Vreasy\Utils\Locale;

class Vreasy_Helper_FieldRules extends Zend_View_Helper_Abstract
{
    public $rules = [];

    public function fieldRules()
    {
        return $this;
    }
}
