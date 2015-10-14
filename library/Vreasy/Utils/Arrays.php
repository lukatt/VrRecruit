<?php

namespace Vreasy\Utils;

abstract class Arrays
{
    public static function intersect_key_recursive()
    {
        $args = func_get_args();
        $firstSet = array_shift($args);
        $otherSets = $args;
        foreach ($otherSets as $idx => $secondSet) {
            $diff = static::diff_key_recursive($firstSet, (array) $secondSet);
            $intersection = static::diff_key_recursive($firstSet, (array) $diff);
            $firstSet = $intersection;
        }
        return $intersection ?: $firstSet;
    }

    public static function replace_key_recursive($a, $alias, $name)
    {
        if (array_key_exists($alias, $a)) {
            $a[$name] = array_key_exists($name, $a) ? $a[$name] : $a[$alias];
            unset($a[$alias]);
        }
        foreach ($a as $k => $value) {
            if (is_array($value)) {
                $a[$k] = static::replace_key_recursive($value, $alias, $name);
            }
        }
        return $a;
    }

    public static function diff_key_recursive()
    {
        $args = func_get_args();
        $minuend = array_shift($args);
        $subtrahends = $args;
        foreach ($subtrahends as $idx => &$sub) {
            foreach ($sub as $key => $value) {
                if (is_array($value)
                    && array_key_exists($key, $minuend)
                    && is_array($minuend[$key])
                ) {
                    $minuend[$key] = self::diff_key_recursive($minuend[$key], $value);
                    unset($sub[$key]);
                }
            }
        }
        array_unshift($subtrahends, $minuend);

        return call_user_func_array('array_diff_key', $subtrahends);
    }

    public static function find($arrayOrObject, $key, $value)
    {
        $item = null;
        foreach ($arrayOrObject as $object) {
            if (is_object($object)) {
                $found = $value == $object->$key;
            } else {
                $found = $value == $object[$key];
            }
            if ($found) {
                $item = $object;
                break;
            }
        }
        return $item;
    }

    public static function extractProperty($array, $property, $opt = [])
    {
        $allowsEmptyValues = @$opt['allowsEmptyValues'] ? true : false;

        $extraction = array_map(
            function ($i) use ($property) {
                if (is_object($i)) {
                    if (property_exists($i, $property)) {
                        return $i->$property;
                    }
                } elseif (isset($i[$property])) {
                    return $i[$property];
                }
                return false;
            },
            $array ?: []
        );
        return $allowsEmptyValues ? $extraction : array_filter($extraction);
    }

    /**
     * Return an array containing elements of $array indexed by $property
     *
     * WARNING: not unique $property values will shorten the $indexedCollection overwriting a duplicated value.
     *
     * @param $array
     * @param $property
     *
     * @return array
     */
    public static function indexByProperty($array, $property)
    {
        $indexedCollection = [];
        foreach($array as $item) {
            $indexedCollection[$item->$property] = $item;
        }

        return $indexedCollection;
    }
}
