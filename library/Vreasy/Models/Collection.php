<?php
// TODO: Rename this bitch and move to utils

namespace Vreasy\Models;
use Vreasy\FieldPluggable;

class Collection extends \ArrayObject implements \JsonSerializable, \Serializable
{
    public $field;
    public $classType;
    public $serializeAsProxy = false;
    public $isComposite = false;

    public function __construct($field, $classType, $array = [])
    {
        $this->field = $field;
        if ($array) {
            $classType = static::getClassTypeOf($array);
            foreach ($array as $input) {
                if ($this->isComposite && !is_a($input, $this->classType)) {
                    $array[$k] = $input = $this->newCompositeInstance($input);
                }

                if ($input instanceof FieldPluggable) {
                    $input->setTargetField($this->field);
                }
            }
        }
        $this->classType = $classType;
        parent::__construct($array);
    }

    public function append($value)
    {
        if ($this->isComposite && !is_a($value, $this->classType)) {
            $value = $this->newCompositeInstance($value);
        }

        if ($value instanceof FieldPluggable) {
            $value->setTargetField($this->field);
        }
        return parent::append($value);
    }

    public function offsetSet($offset, $value)
    {
        if ($this->isComposite && !is_a($value, $this->classType)) {
            $value = $this->newCompositeInstance($value);
        }
        if ($value instanceof FieldPluggable) {
            $value->setTargetField($this->field);
        }
        return parent::offsetSet($offset, $value);
    }

    public function offsetGet($i = null)
    {
        return parent::offsetGet($i ?: 0);
    }

    public function exchangeArray($array)
    {
        foreach ($array as $k => $input) {
            if ($this->isComposite && !is_a($input, $this->classType)) {
                $array[$k] = $input = $this->newCompositeInstance($input);
            }
            if ($input instanceof FieldPluggable) {
                $input->setTargetField($this->field);
            }
        }
        return parent::exchangeArray($array);
    }

    public function newCompositeInstance()
    {
        $ref = new \ReflectionClass($this->classType);
        return $ref->newInstanceArgs(func_get_args());
    }

    public function getClassType($options = [])
    {
        $using = null;
        extract($options, EXTR_IF_EXISTS);

        if ($this->usesLateClassResolution() && $using) {
            if (method_exists($using, $this->classType)) {
                return call_user_func([$using, $this->classType]);
            } else {
                $prop = $this->classType;
                return $using->$prop;
            }
        } else {
            return $this->classType;
        }
    }

    /**
     * Returns true when the wrapped object type depends on other fields or methods.
     *
     * It exposes the proxy capabilities of resolving the class name of the wrapped
     * object using a property or a method call.
     * This way the Base#__set method, that controls how to set values inside the models
     * could tell if data validation checks are needed.
     *
     * For composite and late-resolved collection objects, there will be no type validation.
     *
     * @return [type] [description]
     */
    public function usesLateClassResolution()
    {
        return !class_exists($this->classType);
    }

    public function jsonSerialize()
    {
        $json = [];
        foreach ($this as $value) {
            if (method_exists($value, 'jsonSerialize')) {
                $json[] = $value->jsonSerialize();
            } else {
                $json[] = $value;
            }
        }
        return $json;
    }

    public function isEmpty()
    {
         return !$this->isPresent();
    }

    public function isPresent()
    {
         return !!$this->count();
    }

    public static function getClassTypeOf($storage)
    {
        if ($storage instanceof Collection) {
            if (($it = $storage->getIterator()) && $it->valid()) {
                return static::getClassTypeOf($it->current());
            }
        } elseif (is_array($storage) && $storage) {
            return static::getClassTypeOf(array_shift($storage));
        } else {
            return get_class($storage);
        }
    }

    public function __toString()
    {
        // Some array methods need to convert the values to strings for comparison
        return spl_object_hash($this);
    }

    public function __clone()
    {
        $clones = [];
        foreach ($this as $key => $value) {
            $clones[$key] = is_object($value) ? (clone $value) : $value;
        }
        $this->exchangeArray($clones);
    }

    public function clear()
    {
        $this->exchangeArray([]);
    }
}
