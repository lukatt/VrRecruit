<?php

namespace Vreasy;

interface HasAttributes
{
    /*
     * An array ['name' => 'value'],
     * with the attributes of this instance.
     */
    public function attributes();

    /*
     * Static way of getting a array with the names of
     * the attributes of the implementer.
     */
    public static function attributeNames();
}
