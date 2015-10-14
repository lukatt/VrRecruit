<?php

namespace Vreasy\Presenters\Traits;

use Robbo\Presenter\Presenter;
use Vreasy\Presenters\Interfaces\ObjectGettable;

/**
 * Provides access to the decorated component
 *
 * ## Nested Presenter's Object Access
 *
 * Provides a way to access the object being "presented" (decorated), even if the current presenter
 * is decorating other presenters.
 *
 * Classes using this trait MUST implement ObjectGettable interface.
 * @see Vreasy\Presenters\Interfaces\ObjectGettable
 */
trait ObjectGetter
{
    /**
     * Gets the original object being presented
     *
     * @return object The origin object being presented
     */
    public function getObject()
    {
        if (isset($this->object) && $object = $this->object) {
            if ($object instanceof ObjectGettable) {
                return $object->getObject();
            } elseif ($object instanceof Presenter) {
                return $object->object;
            } else {
                return $object;
            }
        }
    }
}
