<?php

namespace Vreasy;

interface FieldPluggable
{
    public function setTargetField($name);
    public static function getPropertyForTargetField($object = null);
}
