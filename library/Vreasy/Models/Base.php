<?php
namespace Vreasy\Models;

use Vreasy\DateTime;
use Vreasy\HasAttributes;
use Vreasy\Initializable;
use Vreasy\Models\Collection;
use Vreasy\Models\One;
use Vreasy\Models\Many;
use Vreasy\Models\HasAssociations;
use Vreasy\Models\Traits\Validation;
use Vreasy\Models\Traits\Dirty;
use Vreasy\Models\Traits\Timestampable;
use Doctrine\Common\Inflector\Inflector;
use Vreasy\Exceptions\InvalidAssociationTypeException;

/**
 * This class represent the Base Model of our framework
 *
 * TODO: document this class
 *
 * @see also Persistence, Stateable and Timestampable Traits
 *
 * This class proxy each valid Zend_Db method
 * here are the most used ones across the Application
 * TODO: wrap those calls in methods like save and destroy in order to remove ZF from the knowledge of this project
 * @method \Zend_Db_Statement query()
 * @method array|\Zend_Db_Table_Rowset fetchAll()
 * @method bool update()
 * @method bool insert()
 * @method int lastInsertId()
 * @method fetchOne()
 * @method fetchCol()
 * @method delete()
 */
class Base implements \Serializable, \JsonSerializable, HasAttributes, HasAssociations
{
    use Validation;
    use Dirty;

    const ASSOCIATION_KEY = '_assoc_';
    const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private $_destroyed = false;
    private $_attributesCache = [];
    private $_serializedFields = [];
    // holds references of magic methods for getting the registered Many and One relations
    // configured in the constructor
    private $_methods = [];

    public function __construct() {}

    /**
     * Overload to allow $this->name = $value
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function __set($name, $value)
    {
        $objectVars = $this->_attributesCache ?: ($this->_attributesCache = get_object_vars($this));
        if (array_key_exists($name, $objectVars)) {
            $value = static::castToNative($value);
            if ($this->$name !== $value) {
                $this->dirtyChange($name);
            }
            $this->$name = $value;
            return;
        } else {
            $field = static::ASSOCIATION_KEY . $name;
            if (array_key_exists($field, $objectVars)) {
                if ($value instanceof Collection) {
                    // Value is an association already
                    $this->$field = $value;
                    $this->staleAttributesCache();
                    return;
                } else {
                    if ($this->$field instanceof Collection) {
                        if ($this->$field->serializeAsProxy) {
                            if (is_string($value)) {
                                $value = @unserialize($value);
                            }
                        }

                        $type = $this->$field->getClassType(['using' => $this]);
                        $toBeAssigned = [];
                        if ($value) {
                            if ($this->$field instanceof One) {
                                // Will replace since it is a One
                                $toBeAssigned = [$value];
                            } elseif (is_array($value) || $value instanceof \Traversable) {
                                // It is going to replace whatever is in the collection
                                if (in_array('Traversable', class_parents($type))) {
                                    // When the inner type of the collection behaves like an array
                                    // then these array's items are set as values
                                    $toBeAssigned = [];
                                    foreach ($value as $idx => $v) {
                                        if ($v || is_array($v) || $v instanceof \Traversable) {
                                            $toBeAssigned[$idx] = $v;
                                        }
                                    }
                                } else {
                                    $toBeAssigned = array_filter((array)$value);
                                }
                            } elseif ($this->$field instanceof Many) {
                                $toBeAssigned = (array) $value;
                            }
                        }

                        // For composite and late-resolved collection objects, there will be no type
                        // validation, since it is not possible to detect at the time of setting the
                        //  values the required type of the data.
                        if ($this->$field->isComposite || $this->$field->usesLateClassResolution()) {
                            $this->$field->exchangeArray($toBeAssigned);
                        } else {
                            // For all the regular collection associations
                            // we need to check that the values being set inside the wrapper
                            // are of the correct type expected by the One/Many proxy object.
                            $valuesWithInvalidType = array_filter(
                                $toBeAssigned,
                                function($o) use($type) { return !is_a($o, $type);}
                            );
                            if (!$valuesWithInvalidType) {
                                $this->$field->exchangeArray($toBeAssigned);
                            } else {
                                $invalidData = current($valuesWithInvalidType);
                                throw new InvalidAssociationTypeException(
                                    sprintf('Could not set the `%1$s` field because of'
                                        .' the given data type (%2$s) does not match the one in the'
                                        .' %3$s model (%4$s).',
                                        $name,
                                        is_object($invalidData)
                                            ? get_class($invalidData)
                                            : gettype($invalidData),
                                        get_class($this),
                                        $type
                                    )
                                );
                            }
                        }
                        $this->staleAttributesCache();
                        return;
                    }
                }
            } elseif (false !== strpos($name, '_')) {
                $this->__set(Inflector::camelize($name), $value);
            }
        }
        // When the property is neither defined nor it is an association the it's dynamic property
        $this->$name = $value;
        $this->staleAttributesCache();
    }

    /**
     * Returns $this->name
     *
     * Encapsulates associations.
     *
     * @param $name
     * @return
     */
    public function __get($name)
    {
        $objectVars = $this->_attributesCache ?: ($this->_attributesCache = get_object_vars($this));
        if (array_key_exists($name, $objectVars)) {
            return $this->$name;
        }

        $field = static::ASSOCIATION_KEY . $name;
        if (array_key_exists($field, $objectVars)) {
            return $this->$field;
        }

        // Lets try to detect snake-cased properties and convert them to camel-cased ones
        if (false !== strpos($name, '_')) {
            $field = static::ASSOCIATION_KEY . Inflector::camelize($name);
            if (array_key_exists($field, $objectVars)) {
                return $this->$field;
            }
        }
    }

    public function __isset($name)
    {
        if ($val = $this->$name) {
            return true;
        }

        $field = static::ASSOCIATION_KEY . $name;
        if ($val = $this->$field) {
            return true;
        }

        // Lets try to detect snake-cased properties and convert them to camel-cased ones
        if (false !== strpos($name, '_')) {
            $field = static::ASSOCIATION_KEY . Inflector::camelize($name);
            if ($val = $this->$field) {
                return true;
            }
        }

        return false;
    }

    public function __unset($name)
    {
        if (isset($this->$name)) {
            unset($this->$name);
        }

        $field = static::ASSOCIATION_KEY . $name;
        if (isset($this->$field)) {
            unset($this->$field);
        } elseif (false !== strpos($name, '_')) {
            $field = static::ASSOCIATION_KEY . Inflector::camelize($name);
            if (isset($this->$field)) {
                unset($this->$field);
            }
        }
        $this->staleAttributesCache();
    }

    public function staleAttributesCache()
    {
        $this->_attributesCache = [];
    }

    public function getAttributesCache()
    {
        return $this->_attributesCache ?: ($this->_attributesCache = get_object_vars($this));
    }

    public function serialize()
    {
        return serialize($this->attributesForDb());
    }

    public function unserialize($data)
    {
        $this->__construct();
        $temp = $this->dirtyTrackChanges;
        $this->dirtyTrackChanges = false;
        static::hydrate($this, unserialize($data));
        $this->dirtyTrackChanges = $temp;
    }

    public function attributes($options = [])
    {
        $filterOutAssociations = false;
        $filterOutSerialized = false;
        $filterOutComposites = false;
        $filterOutAllAssociations = false;
        $onlyForeignKeys = false;
        $serialized = false;
        extract($options, EXTR_IF_EXISTS);

        if ($filterOutAllAssociations) {
            $filterOutSerialized = $filterOutAssociations = $filterOutComposites = true;
        }

        $ref = new \ReflectionClass(get_class($this));
        $properties = $ref->getProperties(\ReflectionProperty::IS_PROTECTED);
        $properties = array_filter(
            $properties,
            function($p) { return 0 !== strpos($p->getName(), 'dirty'); }
        );

        $v = [];
        foreach ($properties as $key => $w) {
            $name = $w->getName();
            $value = $this->$name;

            if ($value instanceof Collection && $value->serializeAsProxy) {
                if ($value instanceof One) {
                    $v[$name] = $value->getAssociation();
                } elseif ($value instanceof Many) {
                    $v[$name] = $value->getCollection();
                }
                if ($serialized) {
                    $v[$name] = $value->isPresent() ? serialize($v[$name]) : null;
                }
            } else {
                $value = static::castToNative($value);
                $v[$name] = $value;
            }

            if ($value instanceof Collection && $value->isComposite) {
                if ($value->isEmpty()) {
                    unset($v[$name]);
                }
            }

            // Filter out all the Associations so they don get "saved"
            if ($filterOutAssociations) {
                if ($value instanceof Collection
                    && !$value->serializeAsProxy
                    && !$value->isComposite
                ) {
                    unset($v[$name]);
                }
            }

            if ($filterOutSerialized) {
                if ($value instanceof Collection && $value->serializeAsProxy) {
                    unset($v[$name]);
                }
            }

            if ($filterOutComposites) {
                if ($value instanceof Collection && $value->isComposite) {
                    unset($v[$name]);
                }
            }

            if ($onlyForeignKeys) {
                if ('id' != $name && false === strpos($name, '_id')) {
                    unset($v[$name]);
                }
            }
        }
        return $v;
    }

    public function attributesForDb()
    {
        return $this->attributes(['filterOutAssociations' => true, 'serialized' => true]);
    }

    private function getLeadingAttributeFromName($name, $pos)
    {
        $attr = substr($name, 0, $pos);
        // Remove trailing underscore if found
        if (strrpos($attr, '_') == (strlen($attr) - 1)) {
            $attr = substr($name, 0, strlen($attr) - 1);
        }
        return $attr;
    }

    /**
     * Overloading this methods allows to call undefined method on $this.
     * For instance $this->somethingPreviouslyWas
     * @param string $name the undefined method that was called
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // TODO: to speed up this guy we can check regex
        if ($pos = strripos($name, 'willChange')) {
            $this->dirtyChange($this->getLeadingAttributeFromName($name, $pos));

        } elseif ($pos = strripos($name, 'previouslyWas')) {
            return $this->getPreviousChangeFor($this->getLeadingAttributeFromName($name, $pos));

        } elseif ($pos = strripos($name, 'previouslyDidChange')) {
            return $this->propertyPreviouslyDidChange(
                $this->getLeadingAttributeFromName($name, $pos)
            );

        } elseif ($pos = strripos($name, 'was')) {
            return $this->getLastChangeFor($this->getLeadingAttributeFromName($name, $pos));

        } elseif ($pos = strripos($name, 'didChange')) {
            return $this->propertyDidChange($this->getLeadingAttributeFromName($name, $pos));

        } elseif (array_key_exists($name, $this->_methods)) {
            array_unshift($arguments, $this->_methods[$name]['property']);
            return call_user_func_array([$this, $this->_methods[$name]['factoryMethod']], $arguments);
        } else {
            return static::__callStatic($name, $arguments);
        }
    }

    public function withoutTrackingChangesDo(\Closure $closure)
    {
        $possibleException = null;
        $oldTrackChanges = $this->dirtyTrackChanges;
        $this->dirtyTrackChanges = false;
        $closure = $closure->bindTo($this);
        try {
            $ret = $closure();
        } catch (\Exception $e) {
            $possibleException = $e;
        }
        $this->dirtyTrackChanges = $oldTrackChanges;
        if ($possibleException) {
            throw $possibleException;
        }
        return $ret;
    }

    public function ignoreWhenTrackingChanges($attributes)
    {
        // $this->_ignoreTrackingFields = $attributes;
    }

    /**
     * Delegates methods to Zend_db for easy db layer access
     */
    public static function __callStatic($name, $arguments)
    {
        // TODO: Stop coupling the models against the DB
        return call_user_func_array(
            [\Zend_Registry::get('Zend_Db'), $name], $arguments
        );
    }

    public static function instanceWith($params, $classType = null, $dirtyTrackChanges = false)
    {
        if ($classType) {
            $object = (new \ReflectionClass($classType))->newInstance();
        } else {
            $object = new static();
            $classType = get_called_class();
        }

        $oldDirtyTrackChanges = @$object->dirtyTrackChanges;
        $object->dirtyTrackChanges = (bool) $dirtyTrackChanges;
        try {
            if (method_exists($classType, 'hydrate')) {
                $object = call_user_func([$classType, 'hydrate'], $object, $params);
            } else {
                $object = static::hydrate($object, $params);
            }

            if ($object instanceof Initializable) {
                $object->initialize();
            }
            $object->dirtyTrackChanges = $oldDirtyTrackChanges;
        } catch (\Exception $e) {
            $object->dirtyTrackChanges = $oldDirtyTrackChanges;
            throw $e;
        }
        return $object;
    }

    /**
     * Sets all the properties of $instance contained in $params
     *
     * @param $instance Base
     * @param array $params
     * @return Base
     */
    public static function hydrate($instance, $params)
    {
        /*
         * BUG:
         * If $params is a Collection proxy of class Base,
         * the array casting returns an array with one element, i.e. [Base],
         * instead of the expected associative array.
         * This does not allow $instance to be hydrated with $params
         */
        $params = (array)$params ?: [];
        $classType = get_class($instance);

        foreach ($params as $k => $v) {
            if ($instance->$k instanceof Collection && $v) {
                if ($instance->$k instanceof One) {
                    $associationClass = $instance->$k->getClassType(['using' => $instance]);
                    $association = null;
                    if (!($v instanceof One)
                        && !is_a($v, $associationClass)
                        && !is_string($v)
                    ) {
                        // When dealing with an object that comes from the DB,
                        // it is mandatory to hydrate it in order to detect what changed.
                        // A common error would be to use instanceWith instead, since this method
                        // does not takes into account the existing values of the properties.
                        if ($instance->$k->isPresent() && is_object($instance->$k->getAssociation())) {
                            $association = clone $instance->$k->getAssociation();

                            if (method_exists($association, 'getArrayCopy')) {
                                // Remove all the keys that were not set when the One association behaves
                                // like an immutable collection
                                $toDelete = array_diff_key(
                                    (array) $association->getArrayCopy(),
                                    (array) $v
                                ) ?: [];
                                $old = $association->getArrayCopy();
                                foreach (array_keys($toDelete) as $assocIdx) {
                                    unset($association[$assocIdx]);
                                }
                            }

                            //In case the class has no hydrate use the one from Base
                            if (method_exists($associationClass, 'hydrate')) {
                                call_user_func_array(
                                    [$associationClass, 'hydrate'],
                                    [
                                        $association,
                                        $v
                                    ]
                                );
                            } else {
                                Base::hydrate($association, $v);
                            }
                        } else {
                            $association = Base::instanceWith($v, $associationClass, true);
                        }
                    }
                    $instance->$k = $association ?: $v;
                } elseif ($instance->$k instanceof Many) {
                    $collectionClass = $instance->$k->getClassType(['using' => $instance]);
                    $collection = [];
                    if (!($v instanceof Many) && !is_string($v)) {
                        foreach ($v as $idx => $a) {
                            if (!is_a($a, $collectionClass) && !is_string($a)) {
                                $collection[$idx] = Base::instanceWith($a, $collectionClass, true);
                            } else {
                                $collection[$idx] = $a;
                            }
                        }
                    }
                    $instance->$k = $collection ?: $v;
                }
            } else {
                $v = static::castToNative($v);
                if (method_exists($instance, 'dirtyChange') && $instance->$k != $v) {

                    // Now lets make sure that for the cases where value is an array
                    // and the association is handling the collection as an immutable set,
                    // the conversion is done so to compare it agains the object.
                    // Otherwise the incoming array will always be different
                    // from a aggregate object (@see Vreasy\Models\RoomConfig).
                    if (is_array($v)
                        && !$instance->$k instanceof Collection
                        && is_object($instance->$k)
                    ) {
                        if ($instance->$k != Base::instanceWith($v, $instance->$k)) {
                            $instance->dirtyChange($k);
                        }
                    } else {
                        $instance->dirtyChange($k);
                    }
                }
                $instance->$k = $v;
            }
        }
        return $instance;
    }

    /**
     * Casts the value into a native integer, float, string or boolean value.
     *
     * @param  string|integer|float|boolean $value The given value to cast
     * @return string|integer|float|boolean The value in its native type
     */
    private static function castToNative($value)
    {
        if (is_numeric($value)
            && ('0' === $value || 0 !== strpos($value, '0') || !ctype_digit((string) $value))
        ) {
            // PHP has a precision issue when comparing floats, bccomp allows to better compare
            // floats until certain number of decimals (by default is 0)
            if (($tempVal = ((int)(float)$value)) && "$tempVal" == $value) {
                // Is an int
                $value = 0 !== strpos($value, '+') ? $tempVal : $value;
            } elseif (($tempVal = ((float)$value)) && bccomp("$tempVal", $value, 8) == 0) {
                // Is a float
                $value = $tempVal;
            } elseif (!(int)$value) {
                $value = 0;
            }
        } elseif (is_string($value)) {
            $value = 'false' == $value ? false : ('true' == $value ? true : $value);
        }
        return $value;
    }

    public function isNew()
    {
        return !$this->id;
    }

    public function isDestroyed()
    {
        return $this->_destroyed;
    }

    public function setDestroyed($value)
    {
        $this->_destroyed = $value;
    }

    public function destroy()
    {
        // First argument is the $succeed param, to signal the outcome of the destruction
        $args = func_get_args() ?: [false];
        list($succeed) = $args;
        $this->_destroyed = $succeed;
    }

    public function jsonSerialize()
    {
        return $this->attributes();
    }

    public static function attributeNames()
    {
        $ref = new \ReflectionClass(get_called_class());
        $properties = $ref->getProperties(\ReflectionProperty::IS_PROTECTED);

        // Remove the dirty-trait's fields
        $properties = array_filter(
            $properties,
            function($p) { return 0 !== strpos($p->getName(), 'dirty'); }
        );
        $v = [];
        foreach ($properties as $key => $w) {
            $v[] = $w->getName();
        }
        return $v;
    }

    public static function attributeForeignKeysNames()
    {
        $v = [];
        foreach (static::attributeNames() as $name) {
            if ('id' == $name || false !== strpos($name, '_id')) {
                $v[] = $name;
            }
        }
        return $v;
    }


    public function __toString()
    {
        // Some array methods need to convert the values to strings for comparition
        return spl_object_hash($this);
    }

    public function serializeOne($field, $classOrInstance, $isComposite = false)
    {
        $this->_serializedFields[$field] = null;
        $assoc = $this->hasOne($field, $classOrInstance);
        $assoc->serializeAsProxy = true;
        $assoc->isComposite = $isComposite;
        return $assoc;
    }

    public function serializeMany($field, $class, $isComposite = false)
    {
        $this->_serializedFields[$field] = null;
        $assoc = $this->hasMany($field, $class);
        $assoc->serializeAsProxy = true;
        $assoc->isComposite = $isComposite;
        return $assoc;
    }

    public function attributesSerialized()
    {
        $serializedFields = [];
        $fields = array_keys($this->_serializedFields);
        foreach ($fields as $name) {
            $value = $this->$name;
            if ($value instanceof One) {
                $serializedFields[$name] = $value->getAssociation();
            } elseif ($value instanceof Many) {
                $serializedFields[$name] = $value->getCollection();
            }
        }
        return $serializedFields;
    }

    /**
     * Declares a one to many or many to many association, at the current object's $property
     * and a collection of many items of type $class
     *
     * @param string $property to define $this->property
     * @param string $class $this->property instanceof $class
     * @return object
     */
    public function hasMany($property, $class)
    {
        // TODO: check the new Persistence Trait for improve this guy
        // Unsetting the property will force to call the magic methods afterwards
        // so we can hook in and do our stuff
        unset($this->$property);
        $field = static::ASSOCIATION_KEY . $property;
        $this->$field = new Many($property, $class);
        $this->injectGetterFor($property, 'collection');
        $this->staleAttributesCache();
        return $this->$field;
    }

    /**
     * Declares a 1:1 association, at the current object's $property and one items of type $class
     *
     * @param string $property to define $this->property
     * @param mixed $classOrInstance
     * @return mixed new property
     */
    public function hasOne($property, $classOrInstance)
    {
        // Unsetting the property will force to call the magic methods afterwards
        // so we can hook in and do our stuff
        unset($this->$property);
        $field = static::ASSOCIATION_KEY . $property;

        $oldDirtyTrackChanges = $this->dirtyTrackChanges;
        $this->dirtyTrackChanges = false;

        if (is_object($classOrInstance)) {
            $classType = get_class($classOrInstance);
            $this->$field = new One($property, $classType, $classOrInstance);
        } else {
            // An empty One association
            $this->$field = new One($property, $classOrInstance);
        }
        $this->injectGetterFor($property, 'association');
        $this->staleAttributesCache();
        $this->dirtyTrackChanges = $oldDirtyTrackChanges;

        return $this->$field;
    }

    public function belongsTo($field, $class)
    {
        // FIXME: As of now it is the same as the hasOne,
        // but logically it isn't the same. The difference is in which side of the
        // association  the "foreign key" is.
        // Explanation: http://guides.rubyonrails.org/association_basics.html
        return $this->hasOne($field, $class);
    }

    /**
     * Instantiate a property as a DateTime
     *
     * @param string $property to define $this->property
     */
    public function hasDate($property)
    {
        $this->hasOne($property, 'Vreasy\DateTime');
        $this->$property->isComposite = true;
    }

    private function injectGetterFor($property, $entityType)
    {
        $method = 'get' . Inflector::classify($property);
        if (!method_exists($this, $method)) {
            $this->_methods[$method] = ['factoryMethod' => $entityType . 'Getter', 'property' => $property];
        }
    }

    private function injectSetterFor($property, $entityType)
    {
        $method = 'set' . Inflector::classify($property);
        if (!method_exists($this, $method)) {
            $this->_methods[$method] = ['factoryMethod' => $entityType . 'Setter', 'property' => $property];
        }
    }

    /**
     * @param string $property
     *
     * @return mixed
     */
    protected function collectionGetter($property, $forceLoad = false)
    {
        $associationProperty = static::ASSOCIATION_KEY . $property;
        if ($this->$associationProperty->isEmpty() || $forceLoad) {
            $this->$associationProperty->clear();
            static::eagerLoad([$this], ['including' => [$property]]);
        }

        return $this->$associationProperty->getCollection();
    }

    /**
     * @param string $property
     *
     * @return mixed
     */
    protected function associationGetter($property, $forceLoad = false)
    {
        if ($this->$property->isEmpty() || $forceLoad) {
            $this->$property->clear();
            static::eagerLoad([$this], ['including' => [$property]]);
        }

        return $this->$property->getAssociation();
    }

    public static function orderById($a, $b)
    {
        return ((int)$a->id) - ((int)$b->id);
    }

    public static function compareObject($a, $b)
    {
        if ($a->isNew() && $b->isNew()) {
            return strcasecmp((string)$a, (string)$b);
        } elseif ($a->id == $b->id) {
            return 0;
        } elseif ($a->id > $b->id) {
            return 1;
        } else {
            return -1;
        }
    }

    public function __clone()
    {
        $clones = [];
        $this->staleAttributesCache();
        foreach (static::attributeNames() as $name) {
            if (($value = $this->$name) && $value instanceof Collection) {
                $clones[$name] = (clone $value);
            }
        }
        static::hydrate($this, $clones);
    }

}
