<?php

namespace Vreasy\Zend;

class VreasyView extends \Zend_View
{
    /**
     * This view was created because it is needed to add some extra functionality ot the clone
     * method.
     *
     * The way views work, when trying to access a helper it will check if it is loaded in the view.
     * The first time, it will create a helper view pointing to the parent view.
     *
     * When cloning a view if there is any helper previously loaded, this is pointing to the view
     * we are cloning from and not to the new view object. This brings issues when storing
     * variables in the view and expecting to be used again in the helper view. Since the view
     * points to the view it was cloned from, these new variables won't be in the helpers view.
     *
     * Since Zend has no way of unloading a helper, we expand the functionality of the clone by
     * reseting the '_helper' property of the view object. This will trigger the creation of the
     * helper view again.
     */

    public function __clone()
    {
       /**
        * Since the _helper property is private, we will need to use ReflectionClass on the parents
        * class, change its accessibility and reset it.
        */

        $refObj = new \ReflectionClass('\Zend_View_Abstract');
        $_helperProp = $refObj->getProperty('_helper');
        $_helperProp->setAccessible(true);
        $_helperProp->setValue($this, []);
        $_helperProp->setAccessible(false);
    }
}
