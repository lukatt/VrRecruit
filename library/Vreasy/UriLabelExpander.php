<?php
namespace Vreasy;

use Vreasy\Exceptions\NotImplementedException;

/**
* Label Expansion with Dot-Prefix: {.var}
* RFC 6570 URI Template implementation
* http://tools.ietf.org/html/rfc6570
*
* X{.keys*}          X.semi=%3B.dot=..comma=%2C
*/
class UriLabelExpander
{
    protected static $labels = [];

    /**
     * Sets the labels to be recognized by the expander.
     */
    public static function setLabels($labels = [])
    {
        static::$labels = $labels;
    }

    public static function expand($uriOrParams, $char = '.')
    {
        if (!static::$labels) {
            throw new NoPropertyException('There are no configured labels to expand');
        }

        $ret = [];
        if ((is_array($uriOrParams) || $uriOrParams instanceof \Traversable)) {
            foreach ($uriOrParams as $key => $value) {
                $expansion = mb_split(preg_quote($char), $key);
                if (count($expansion) > 1) {
                    $var = array_shift($expansion);
                    if (in_array($var, static::$labels)) {
                        $label = implode($char, $expansion);
                        $ret[$var][$label] = $value;
                    }
                    else {
                        $ret[$key] = $value;
                    }
                }
                else {
                    $ret[$key] = $value;
                }
            }
        }
        else {
            throw new NotImplementedException("Expansion of URI string not supported");
        }
        return $ret;
    }
}
