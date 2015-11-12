<?php

namespace Vreasy;

class NullObject implements \ArrayAccess
{
    public function __set($attribute, $value)
    {
        return;
    }

    public function __call($method, $args)
    {
        return;
    }

    public function __get($attribute)
    {
        return;
    }

    public function __isset($attribute)
    {
        return false;
    }

    public function __unset($attribute)
    {
        return;
    }

    public function __toString()
    {
        return '';
    }

    public function offsetExists($offset)
    {
        return false;
    }

    public function offsetGet($offset)
    {
        return '';
    }

    public function offsetSet($offset, $value)
    {
        return;
    }

    public function offsetUnset($offset)
    {
        return;
    }
}
