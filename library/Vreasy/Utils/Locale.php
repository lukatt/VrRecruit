<?php

namespace Vreasy\Utils;

use Vreasy\Models\Language;

class Locale
{
    public static function getLocaleFrom($object)
    {
        $localeString = '';
        if (is_object($object)) {
            if (method_exists($object, 'getLocale')) {
                $localeString = (string) $object->getLocale();
            } elseif (!($object instanceof \Zend_Locale) && method_exists($object, 'getLanguage')) {
                $localeString = (string) $object->getLanguage();
            } elseif (method_exists($object, 'toString') || method_exists($object, '__toString')) {
                $localeString = (string) $object;
            }
        }

        return $localeString ?: ((string) (new Language())->locale);
    }
}
