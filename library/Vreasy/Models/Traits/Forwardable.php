<?php

namespace Vreasy\Models\Traits;

/**
 * Method call & properties delegation
 *
 * ## Method and Property Forwarding
 *
 * Provides a way to forward method messages and access to properties to a delegate object at run time.
 *
 * ### Requirements
 *
 * - ```use Forwardable``` in your class.
 * - If in your class you are using any of the [magic methods](http://php.net/manual/en/language.oop5.magic.php), make sure to correctly alias it and delegate to the Forwardable trait.
 * - Call ```delegate($object, $messages)``` to **setup** which object to want to delegate the property name or method name to.
 *
 * ### Examples
 *
 * A minimal implementation could be:
 *
 * ```php
 * class Reservation
 * {
 *     use Forwardable;
 *
 *     protected $calendar;
 *
 *     public function __construct()
 *     {
 *         $this->delegate($this->calendar, ['isAvailable']);
 *     }
 * }
 * ```
 *
 * With the `Reservation` class from above, the `isAvailable` method or property will be retrieved
 * using the `$this->calendar` object.
 *
 */

trait Forwardable
{
    private $fwdMessages = [];
    static private $fwdClassMessages = [];

    public function delegate($objectOrClass, $messages = [])
    {
        foreach ($messages as $name) {
            if (is_object($objectOrClass)) {
                $this->fwdMessages[$name] = $objectOrClass;
            } else {
                static::$fwdClassMessages[$name] = $objectOrClass;
            }
        }
    }

    public function stopDelegation($objectOrClass, $messages = [])
    {
        if (is_object($objectOrClass)) {
            $fwdMessages = &$this->fwdMessages;
        } else {
            $fwdMessages = &static::$fwdClassMessages;
        }

        // Find the delegated messages for the given object
        $delegationsWithObject = [];
        foreach ($fwdMessages as $name => $subordinateObject) {
            if ($subordinateObject === $objectOrClass) {
                $delegationsWithObject[] = $name;
            }

        }

        // Stop all message delegations for the object if no messages are specified
        $delegationsToStop = count($messages) ? [] : $delegationsWithObject;
        if (count($messages)) {
            foreach ($messages as $name) {
                if (in_array($name, $delegationsWithObject)) {
                    $delegationsToStop[] = $name;
                }
            }
        }
        $fwdMessages = array_diff_key($fwdMessages, array_flip($delegationsToStop));
    }

    public function __call($name, $args)
    {
        if (isset($this->fwdMessages[$name])) {
            $instance = is_object($this->fwdMessages[$name]) ? $this->fwdMessages[$name] : null;
            if ($instance) {
                return call_user_func_array([$instance, $name], $args);
            }
        }
        throw new \BadMethodCallException("Method `$name` does not exist in ".get_called_class());
    }

    public static function __callStatic($name, $args)
    {
        if (isset(static::$fwdClassMessages[$name])) {
            $class = is_string(static::$fwdClassMessages[$name])
                ? static::$fwdClassMessages[$name]
                : null;
            if ($class) {
                return call_user_func_array([$class, $name], $args);
            }
        }
        throw new \BadMethodCallException("Static method `$name` does not exist in ".get_called_class());
    }

    public function __get($name)
    {
        if (isset($this->fwdMessages[$name]) && $instance = $this->fwdMessages[$name]) {
            return $instance->$name;
        } elseif (($params = get_object_vars($this)) && array_key_exists($name, $params)) {
            return $params[$name];
        }
    }

    public function __isset($name)
    {
        return isset($this->fwdMessages[$name]) || array_key_exists($name, get_object_vars($this));
    }

    public function __unset($name)
    {
        if (isset($this->fwdMessages[$name])) {
            unset($this->$name);
        } elseif (isset($this->$name)) {
            unset($this->$name);
        }
    }

    public function __set($name, $value)
    {
        if (isset($this->fwdMessages[$name]) && $instance = $this->fwdMessages[$name]) {
            $instance->$name = $value;
        } else {
            $this->$name = $value;
        }
    }
}
