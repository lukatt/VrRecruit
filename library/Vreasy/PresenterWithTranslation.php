<?php

namespace Vreasy;

use Robbo\Presenter\Presenter;
use Doctrine\Common\Inflector\Inflector;
use Vreasy\Presenters\Traits\ObjectGetter;
use Vreasy\Presenters\Interfaces\ObjectGettable;
use Vreasy\Exceptions\NoPropertyException;
use Vreasy\Models\Traits\Forwardable;
use Vreasy\Models\Collection;
use Vreasy\Query\Builder;

class PresenterWithTranslation extends Presenter implements ObjectGettable, \JsonSerializable
{
    use ObjectGetter;
    use Forwardable {
      Forwardable::__call as __callForwardable;
    }

    protected static $attributesNamesCache = [];
    protected $contentType;
    protected $view;
    protected $hiddenAttributes = [];
    protected $shownAttributes = [];
    protected $hiddenAttributesWhenEmpty = [];
    protected $shownAssociations = [];
    protected $shownForcedAttributes = [];
    protected $fieldRules = ['*'];

    public function __construct($object, $locale = null, $contentType = null, $rules = [])
    {
        parent::__construct($object);
        $this->setView(\Zend_Registry::get('VreasyView'));
        $this->delegate($this->view, ['t', 'translate']);
        $this->delegate($this->view->translate(), ['getLocale', 'setLocale']);

        if ($locale) {
            $this->setLocale($locale);
        }
        $this->contentType = $contentType;
        $this->setFieldRules($rules ?: $this->view->fieldRules()->rules);
    }

    public function setView($view)
    {
        $this->view = $view;
    }

    public function setFieldRules($rules = [])
    {
        $this->fieldRules = $rules;
        $this->applyRules();
    }

    public function hide($attributes)
    {
        $this->hiddenAttributes = (array) $attributes;
        return $this;
    }

    public function __call($name, $args)
    {
        try {
            return $this->__callForwardable($name, $args);
        } catch (\BadMethodCallException $e){
            return parent::__call($name, $args);
        }
    }

    public function __get($name)
    {
        if ($this->hiddenAttributes && in_array($name, $this->hiddenAttributes)) {
            throw new NoPropertyException("Property `{$name}` has been hidden by the presenter.");
        }

        if ($this->shownAttributes && !in_array($name, $this->shownAttributes)) {
            throw new NoPropertyException("Property `{$name}` has not been set to be shown by the presenter.");
        }

        if ($this->hiddenAttributesWhenEmpty
            && in_array($name, $this->hiddenAttributesWhenEmpty)
            // Value must be empty and specifically not zero
            && ((!($value = parent::__get($name)) && 0 !== $value && false !== $value)
                || ($value instanceof Collection && $value->isEmpty()))
        ) {
            throw new NoPropertyException("Property `{$name}` has been hidden when it's empty by the presenter.");
        }

        return parent::__get($name);
    }

    public function jsonSerialize()
    {
        // Collect attribute names to hide
        foreach ($this->hiddenAttributesWhenEmpty as $name) {
            if (!($value = parent::__get($name)) && 0 !== $value && false !== $value) {
                $this->hiddenAttributes[] = $name;
            } elseif ($value instanceof Collection && $value->isEmpty()) {
                $this->hiddenAttributes[] = $name;
            }
        }

        // Remove the hidden attributes from the list of shown attributes if any
        $attributeNames = array_diff(
            $this->shownAttributes
                ? array_intersect($this->attributeNames(), $this->shownAttributes)
                : $this->attributeNames(),
            $this->hiddenAttributes
        );

        // When dealing with associations, ensures that only associations marked to be shown
        // are serialized in the json
        foreach ($attributeNames as $key => $name) {
            if (($value = parent::__get($name))
                && $value instanceof Collection
            ) {
                if ($this->shownAssociations
                    && !in_array('-', $this->shownAssociations)
                    && !in_array('*', $this->shownAssociations)
                    && !in_array($name, $this->shownAssociations)
                ) {
                    unset($attributeNames[$key]);
                }
            }
        }

        $data = [];
        foreach ($attributeNames as $name) {
            if (($value = parent::__get($name))
                && $value instanceof Collection
                && ['-'] == $this->shownAssociations
                && $value->isPresent()
                && !in_array($name, $this->shownAttributes)
            ) {
                // Remove associations that were not explicitly requested
                $valueCopy = clone $value;
                $valueCopy->clear();
                $data[$name] = $valueCopy;
            } else {
                $data[$name] = $this->$name;
            }
        }
        return $data;
    }

    protected function attributeNames()
    {
        if ($attributesNames = @static::$attributesNamesCache[get_called_class()]) {
            return $attributesNames;
        } else {
            if (method_exists($this->getObject(), "attributeNames")) {
                $attributesNames = $this->getObject()->attributeNames();
            } else {
                $ref = new \ReflectionClass(get_class($this->getObject()));
                $properties = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);

                // Remove the dirty-trait's fields that are sometimes injected in some non-base classes
                $properties = array_filter(
                    $properties,
                    function($p) { return 0 !== strpos($p->getName(), 'dirty'); }
                );

                $attributesNames = [];
                foreach ($properties as $key => $w) {
                    $attributesNames[] = $w->getName();
                }
                return $attributesNames;
            }
        }
        static::$attributesNamesCache[get_called_class()] = $attributesNames;
        return $attributesNames;
    }

    protected function applyRules()
    {
        $fields = Builder::expandFieldsInToFields($this->fieldRules);
        if (!in_array('*', $fields) && $fields) {
            $this->shownAttributes = $fields;
        } else {
            $this->shownAssociations = array_diff($fields, ['*']) ?: ['-'];
            $this->shownForcedAttributes = array_intersect(
                $fields,
                $this->hiddenAttributesWhenEmpty
            );
        }
    }
}
