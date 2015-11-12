<?php

namespace Vreasy\Models\Utils;

use Vreasy\Models\Traits\Validation;

class ValidationDecorator
{
    use Validation
    {
        Validation::isValid as __isValid;
    }

    /**
     * The object that it is being decorated
     * @var Vreasy\Base
     */
    public $componentObject;

    public function __construct($componentObject)
    {
        $this->componentObject = $componentObject;
    }

    public function isValid($data = [])
    {
        return $this->__isValid($this->componentObject->attributes());
    }

    public function __set($attribute, $value)
    {
        return $this->componentObject->$attribute = $value;
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->componentObject, $method], $args);
    }

    public function __get($attribute)
    {
        return $this->componentObject->$attribute;
    }

    public function __isset($attribute)
    {
        return isset($this->componentObject->$attribute);
    }

    public function __unset($attribute)
    {
        unset($this->componentObject->$attribute);
    }

    public function __clone()
    {
        $this->componentObject = clone $this->componentObject;
    }

    public function __toString()
    {
        // Some array methods need to convert the values to strings for comparition
        return $this->componentObject->__toString();
    }
}
