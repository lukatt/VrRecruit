<?php

namespace Vreasy\Presenters\Traits;

use Doctrine\Common\Inflector\Inflector;
use Vreasy\Models\Collection;
use Vreasy\Models\One;
use Vreasy\Models\Many;
use Vreasy\Query\Builder;

/**
 * Automatic presenter instantiation for associations.
 *
 * Use this trait to enable a json to detect and instantiate a presenter object for each of its
 * relationships (eg: hasMany, hasOne).
 *
 */
trait AutoloadAssociationPresenters
{
    protected static $autoloadAssociationPresentersCache = [];

    protected $autoloadAssociationPresentersPathPrefix = 'Vreasy\\Presenters\\Json\\';

    public function __get($name)
    {
        $value = parent::__get($name);
        if ($value instanceof Collection
            && $value->isPresent()
            && $jsonPresenterClass = $this->getJsonPresenterClass($name, $value)
        ) {

            $ref = new \ReflectionClass($jsonPresenterClass);
            if ($value instanceof One) {
                $ret = $ref->newInstanceArgs([$value->getAssociation(), null, null, ['*']]);
                $ret->setFieldRules(
                    Builder::extractFieldsRulesFor($this->fieldRules, $name)
                );
                return $ret;
            } elseif ($value instanceof Many) {
                $ret = [];
                foreach ($value as $k => $v) {
                    $ret[$k] = $ref->newInstanceArgs([$v, null, null, ['*']]);
                    $ret[$k]->setFieldRules(
                        Builder::extractFieldsRulesFor($this->fieldRules, $name)
                    );
                }
                return $ret ?: $value;
            }
        }
        return $value;
    }

    public function getJsonPresenterClass($propName, $value)
    {
        $jsonPresenterClass = null;
        $cacheKey = __CLASS__.$propName;
        if (array_key_exists($cacheKey, static::$autoloadAssociationPresentersCache)) {
            $jsonPresenterClass = static::$autoloadAssociationPresentersCache[$cacheKey];
        } else {
            $object = $this->getObject();
            $assocGetter = 'get'.Inflector::classify($propName);
            $assocValue = $object->$assocGetter();
            $classType = $value->getClassType(['using' => $object]);
            $className = (new \ReflectionClass($classType))->getShortName();
            $className = false !== strpos($className, '_')
                ? substr(strrchr($className, '_'), 1)
                : $className;
            $jsonPresenterClass = $this->autoloadAssociationPresentersPathPrefix.$className;
            if (!@class_exists($jsonPresenterClass)) {
                $jsonPresenterClass = null;
            }
            static::$autoloadAssociationPresentersCache[$cacheKey] = $jsonPresenterClass;
        }
        return $jsonPresenterClass;
    }
}
