<?php

namespace Vreasy\Models\Traits;

use Doctrine\Common\Inflector\Inflector;
use Vreasy\Exceptions\NoPropertyException;
use Vreasy\Exceptions\NotImplementedException;
use Vreasy\Models\Collection;
use Vreasy\Models\One;
use Vreasy\Models\Base;
use Vreasy\Models\Many;
use Vreasy\WhereIterator;
use Vreasy\WhereIteratorUsingAfter;
use Vreasy\Query\Builder;
use Vreasy\Utils\Arrays;

/**
 * @see Zend_Db
 *
 * @method \Zend_Db_Adapter_Abstract getAdapter()
 *
 * @method delete()
 * @method fetchAll()
 * @method insert()
 * @method lastInsertId()
 * @method update()
 *
 * @see Base
 *
 * @method isValid()
 * @method attributesForDb()
 * @method isNew()
 * @method static instanceWith()
 */
trait Persistence
{
    private static $persistenceClassName = '';

    /**
     * Finds a collection of objects using the given parameters
     *
     * The parameters used to find the objects, should be compatible with what the Query Builder
     * helper class can understand. @see Vreasy\Query\Builder
     *
     * The available options that will modify how this method will look up objects in the persistent
     * storage are:
     *
     *  **`alias`**: The alias name of the table. By default is `e` (of entity).
     *
     *  **`select`**: The select clause used to retrieve the data from the table.
     *
     *  **`fields`**: A collection of fields to include in the selection.
     *  @see Vreasy\Query\Builder::expandFieldsInToColumns()
     *
     *  **`scopes`**: A collection of name scopes that will be used as an "anchor" in the query,
     *  to filter the results further or `false` to disable any scope.
     *  Named scopes should have a matching static method named `<name>Scope()`, that should return
     *  the results of calling `Vreasy\Query\Builder::expandWhere`. The scopes are going to be joined
     *  with 'AND' logical expressions and **prefixed** in the where clause of the query.
     *  The class also could implement the static method `defaultScopes()`, that should return a
     *  collection of scopes names to be applied in "all" where calls, and to avoid repeating scopes
     *  all over the place.
     *  @see Vreasy\Query\Builder::expandWhere()
     *
     * **`exclusiveScopes`**: This option works exactly the same as the `scopes` one, but it takes
     * precedence over any default scope set. When passing a collection of exclusive scopes, then
     * no other scope will be set apart from those named here.
     *
     * **`limit`**: The limit of objects to return. By default `1000`.
     *
     * **`orderBy`**: An ordered collection of fields to order the results by.
     *
     * **`orderDirection`**: An ordered collection of either 'asc' or 'desc' entries that should
     * match the number and order of those set in the `orderBy` option.
     *
     * **`rawOrderBy`**: To be used only in very weird cases when simple order by clauses are not
     * enough. The index of the value must exist in the direction collection too.
     *
     * **`reverseResults`**: Whether or not return the resulting object's collection in the reverse
     * order. Useful when paginating. When using the `before` scope it will be set to true by the
     * scope method.
     *
     * @param array A collection of parameters compatible with our Query Builder
     * @param array A collection of options that will modify the behavior of the where
     *
     * @return $this[]
     */
    public static function where($params, $opts = [])
    {
        // Make sure all arguments have sane defaults
        // Also when adding new options, make sure to add the value in the `compact` call
        // @see sanitizeOptions
        $objectsFound = [];
        $alias = 'e';
        $fields = ['*'];
        $scopes = [];
        $exclusiveScopes = [];
        $limit = 1000;
        $start = 0;
        $orderBy = [];
        $orderDirection = [];
        $rawOrderBy = [];
        $select = '';
        $unions = [];
        $reverseResults = false;
        $opts['params'] = $params;

        // Watch out for those options where "false" values are valid and make sure to correctly
        // process them below (see `$scopes`).
        $opts = array_replace($opts, static::sanitizeOptions($opts));
        extract(array_filter($opts), EXTR_IF_EXISTS);
        $scopes = @$opts['scopes'] ?: (false === @$opts['scopes'] ? false : $scopes);

        list($builtScopes, $scopesValues) = static::buildNamedScopes($opts);
        // Scopes might have modified some options @see beforeScope()
        $opts = array_replace($opts, static::sanitizeOptions($opts));
        extract(array_filter($opts), EXTR_IF_EXISTS);
        $scopes = @$opts['scopes'] ?: (false === @$opts['scopes'] ? false : $scopes);

        // The unions returns a collection of clauses and the values.
        // The clauses are going to be used when building the whole query to pre-pending to the
        // existing where params to ensure union is also doing the requested filtering
        list($builtUnions, $unionsValues) = static::buildUnions($opts);

        $opts['select'] = $select = self::applyFieldRulesOnSelect($opts);
        $opts = array_replace($opts, static::sanitizeOptions($opts));
        extract(array_filter($opts), EXTR_IF_EXISTS);
        $scopes = @$opts['scopes'] ?: (false === @$opts['scopes'] ? false : $scopes);

        list($where, $values) = Builder::expandWhere(
            $params,
            ['wildcard' => true, 'prefix' => "$alias."]
        );

        if ($where || $builtScopes) {
            $orderByClause = static::getOrderByClause($opts);
            $limitClause = static::getLimitClause($opts);
            $scopesWhere = implode(' AND ', array_filter($builtScopes));
            $where = $where && $scopesWhere
                ? "WHERE ($scopesWhere) AND ($where)"
                : ($where ? "WHERE $where" : "WHERE $scopesWhere");

            $unionClause = static::getUnionClause($where, $builtScopes, $builtUnions, $unionsValues, $opts);
            $sql = "$select $where $unionClause $orderByClause $limitClause";
            if ($res = static::fetchAll($sql, array_merge($values, $scopesValues, $unionsValues))) {
                if (method_exists(get_called_class(), 'instanceWith')) {
                    foreach ($res as $row) {
                        $objectsFound[] = static::instanceWith($row);
                    }
                } else {
                    foreach ($res as $row) {
                        $objectsFound[] = Base::instanceWith($row, get_called_class());
                    }
                }
            }
        }
        return $reverseResults ? array_reverse($objectsFound) : $objectsFound;
    }

    public static function existsWhere($params, $opts = [])
    {
        return (bool) static::where(
            $params,
            array_replace(['limit' => 1], $opts)
        );
    }

    public static function whereIterator($params, $opts = [])
    {
        $it = new WhereIterator(get_called_class());
        return $it->where($params, $opts);
    }

    public static function whereIteratorUsingAfter($params, $opts = [])
    {
        return new WhereIteratorUsingAfter(get_called_class(), $params, $opts);
    }

    protected static function sanitizeOptions($opts = [])
    {
        $alias = 'e';
        $fields = ['*'];
        $scopes = [];
        $exclusiveScopes = [];
        $limit = 1000;
        $start = 0;
        $orderBy = [];
        $orderDirection = [];
        $rawOrderBy = [];
        $select = '';
        $reverseResults = false;
        $unions = [];
        $params = false;

        // Watch out for those options where "false" values are valid and make sure to correctly
        // process them below (see `$scopes`).
        extract(array_filter($opts), EXTR_IF_EXISTS);
        $scopes = @$opts['scopes'] ?: (false === @$opts['scopes'] ? false : $scopes);

        // Normalize collection arguments as a one dimensional array
        // and fix indexes if array_intersect or similar function has been used
        $fields = array_unique(array_flatten(array_values([$fields])));
        $orderBy = array_unique(array_flatten(array_values([$orderBy])));
        $orderDirection = array_flatten(array_values([$orderDirection]));
        $rawOrderBy = array_unique(array_flatten(array_values([$rawOrderBy])));

        // Sanitize column name to avoid injection by closing the back-tick quote
        $alias = str_ireplace('`', '', $alias);
        $fields = str_ireplace('`', '', $fields);
        $limit = min((int) $limit, 1000);
        $start = (int) $start;
        $reverseResults = (bool) $reverseResults;
        $orderBy = str_ireplace('`', '', $orderBy);

        // Validate direction values, allowing `desc` or `asc` only
        foreach ($orderDirection as $i => $dir) {
            $orderDirection[$i] = filter_var(
                $dir,
                FILTER_VALIDATE_REGEXP,
                ['options' => ['regexp' => '/^(asc|desc)$/i' ,'default' => 'ASC']]
            );
        }

        // Avoid having more order direction items than order by columns
        $orderDirection = array_slice($orderDirection, 0, count($orderBy) + count($rawOrderBy));

        $select = $select ?: "SELECT $alias.* FROM `" . static::getTableName() . "` AS `$alias`";
        return compact(
            'alias',
            'fields',
            'scopes',
            'exclusiveScopes',
            'limit',
            'start',
            'orderBy',
            'orderDirection',
            'rawOrderBy',
            'select',
            'reverseResults',
            'unions',
            'params'
        );
    }

    protected static function applyFieldRulesOnSelect($opts = [])
    {
        $alias = '';
        $select = '';
        $fields = [];
        $orderBy = [];
        extract($opts, EXTR_IF_EXISTS);

        $fields = array_unique(array_merge(
            $fields,
            method_exists(get_called_class(), 'attributeForeignKeysNames')
                ? static::attributeForeignKeysNames()
                : []
        ));
        $columns = Builder::expandFieldsInToFields($fields);
        if (!in_array('*', $columns)) {
            $columns = array_merge($columns, $orderBy);
            if (method_exists(get_called_class(), 'attributesForDb')) {
                $object = (new \ReflectionClass(get_called_class()))->newInstance();
                $columns = array_intersect(array_keys($object->attributesForDb()), $columns);
            } elseif (method_exists(get_called_class(), 'attributeNames')) {
                $columns = Builder::expandFieldsInToColumns($fields);
                $columns = array_intersect(static::attributeNames(), $columns);
            }

            $select = str_replace(
                "$alias.*",
                "$alias.`". implode("`, $alias.`", $columns). "`",
                $select
            );
        }
        return $select;
    }

    protected static function persistencePaginationCursors($resourceId, &$opts = [])
    {
        $alias = 'e';
        $orderBy = [];
        $orderDirection = [];

        extract($opts, EXTR_IF_EXISTS);
        $cursorResource = null;

        $tempOpts = $opts;
        unset(
            $tempOpts['exclusiveScopes']['after'],
            $tempOpts['exclusiveScopes']['before'],
            $tempOpts['scopes']['after'],
            $tempOpts['scopes']['before']
        );

        // When the exclusive scope option it's used, we have to make sure that the cursor resource
        // being used wont "carry over" any default scopes, since the intention here was to
        // override this (because of exclusiveScopes).
        if (@$opts['exclusiveScopes']) {
            $tempOpts['scopes'] = false;
        }

        if ($resourceId &&
            $cursorResource = current(static::where(
                array_merge(
                    @$opts['params'] ?: [],
                    ['id' => $resourceId]
                ),
                $tempOpts)
            )
        ) {
            $offsetClauses = [];
            $db = \Zend_Registry::get('Zend_Db');
            foreach ($orderBy as $i => $field) {
                // Use this entity alias if non is found in this orderBy field
                $field = strpos($field, '.') ? $field : "$alias.$field";
                $direction = isset($orderDirection[$i])
                    ? strtoupper($orderDirection[$i])
                    : (@$direction ?: 'ASC');

                $orderDirection[$i] = $direction;
                $symbol = 'ASC' == $direction ? '>' : '<';
                $offsetInitialValue = 'ASC' == $direction ? '0' : PHP_INT_MAX;

                $offsetString = '(';
                for ($j = 0; $j < $i; $j++) {
                    $innerField = $orderBy[$j];
                    if ($offsetValue = static::retrieveOffsetFromGetter(
                        @$cursorResource,
                        $innerField,
                        $opts
                    )) {
                        $offsetString .= "$innerField = ".$db->quote((string) $offsetValue)." AND ";
                    } else {
                        $offsetString .= "$innerField = ".$db->quote((string) $offsetInitialValue)." AND ";
                    }
                }

                // End with the current field:
                if ($offsetValue = static::retrieveOffsetFromGetter(@$cursorResource, $field, $opts)) {
                    $offsetString .= "$field $symbol ".$db->quote((string) $offsetValue).")";
                } else {
                    $offsetString .= "$field $symbol ".$db->quote((string) $offsetInitialValue).")";
                }

                $offsetClauses[] = $offsetString;
            }
            list($scopeWhere, $scopeValues) = Builder::expandWhere([
                '#literal' => implode(' OR ', $offsetClauses)
            ]);
        }
        $opts['orderDirection'] = $orderDirection;
        return $cursorResource ? [$scopeWhere, $scopeValues] : ['', []];
    }

    public static function retrieveOffsetFromGetter($resource, $field, $opts = [])
    {
        if (($offsetGetter = @$opts['offsetGetter']) && $closure = @$offsetGetter[$field]) {
            $closure = $closure->bindTo($resource);
            return $closure();
        } else {
            $alias = 'e';
            extract($opts, EXTR_IF_EXISTS);
            $field = str_replace("$alias.", "", $field);
            return $resource->$field;
        }
    }

    public static function beforeScope($resourceId, &$opts = [])
    {
        $orderBy = [];
        $orderDirection = [];
        extract($opts, EXTR_IF_EXISTS);

        if ($resourceId) {
            $reverseOrderDirection = function($direction) {
                // Reverse the order direction changing `asc` for `desc` and vice versa
                $direction = str_ireplace(
                    'desc',
                    'TEMP',
                    $direction
                );
                $direction = str_ireplace(
                    'asc',
                    'desc',
                    $direction
                );
                $direction = str_ireplace(
                    'TEMP',
                    'asc',
                    $direction
                );
                return $direction;
            };

            foreach ($orderBy as $i => $key) {
                if (!isset($orderDirection[$i])) {
                    $orderDirection[$i] = 'ASC';
                }
            }

            // Now we have to reverse the last order direction, this way the unique key
            // will order the collection
            $opts['orderDirection'] = array_map($reverseOrderDirection, $orderDirection);
            $opts['reverseResults'] = true;

        }
        return static::persistencePaginationCursors($resourceId, $opts);
    }

    public static function afterScope($resourceId, &$opts = [])
    {
        return static::persistencePaginationCursors($resourceId, $opts);
    }

    public static function buildNamedScopes(&$opts = [])
    {
        $scopes = [];
        $exclusiveScopes = [];
        extract($opts, EXTR_IF_EXISTS);

        // Since in the extraction of the options we filter out "falsy" values, lets manually
        // check for a false value for the scope option, which means that no scope should be applied
        $scopes = false === @$opts['scopes'] ? false : $scopes;
        if (false !== $scopes) {
            $scopes = array_replace_recursive(
                (method_exists(get_called_class(), 'defaultScopes')
                    ? @call_user_func_array(get_called_class().'::defaultScopes', [])
                    : []),
                $scopes ?: []
            );
        }

        // When a exclusive scope is set, it overrides the default scopes and any other scope
        // setting that is in place.
        $scopes = $exclusiveScopes ?: ($scopes ?: []);

        $helperPostfix = 'scope';
        $where = [];
        $values = [];
        foreach ($scopes as $name => $arguments) {
            $method = new \ReflectionMethod(get_called_class(), $name . $helperPostfix);
            $arguments = is_array($arguments) ? $arguments : [$arguments];
            if (($paramCount = $method->getNumberOfParameters()) && $paramCount > 1) {
                $arguments = array_pad($arguments, $paramCount - 1, null);
            }
            $arguments[] = &$opts;

            // Execute the scope methods that return the result of an `expandWhere` call.
            // This is the conditions of where clause and a collection of values bind in the where
            list($w, $v) = call_user_func_array(
                get_called_class() . '::' . $name . $helperPostfix,
                array_values($arguments)
            );

            // To avoid names collision when binding values into the where clause, both the where
            // and the values are name-spaced with a simple prefix
            $valueKeys = array_keys($v);
            $prefix = 's'.$name.'_';
            $w = str_replace(
                array_map(function($i){ return ":$i";}, $valueKeys),
                array_map(function($i) use($prefix) { return ":$prefix$i";}, $valueKeys),
                $w
            );

            foreach ($v as $oldKey => $value) {
                $v[$prefix.$oldKey] = $value;
                unset($v[$oldKey]);
            }

            $where[$name] = trim($w);
            $values += (array) $v;
        }
        return [$where, array_filter($values)];
    }

    /**
     * Save the model into the database.
     * See the HOOKS below for adding behaviour in Models using this Traits
     */
    public function save()
    {
        $this->beforeSave();
        if (!$this->isValid()) {
            return false;
        }

        $success = false;
        $tableName = static::getTableName();
        if ($this->isNew()) {
            if (method_exists($this, 'beforeInsert')) {
                $this->beforeInsert();
            }
            if ($success = (bool) static::insert($tableName, $this->attributesForDb())) {
                $this->id = static::lastInsertId();
                $this->afterInsert();
            }
        } else {
            if (method_exists($this, 'beforeUpdate')) {
                $this->beforeUpdate();
            }
            // MySQL DB `update()` returns the number of rows affected.
            // An update hook MUST only run when some row was affected in the db.
            if (static::update($tableName, $this->attributesForDb(), ['id=?' => $this->id])) {
                $this->afterUpdate();
            }
            $success = true;
        }

        if ($success) {
            if (method_exists($this, 'changesApplied')
                && method_exists($this, 'didChange')
                && $this->didChange()
            ) {
                $this->changesApplied();
            }
            $this->afterSave();
        }
        $this->finallyAfterSave();
        return (bool) $success;
    }

    /**
     * Hook called before performing the changes (insert or update) and before checking isValid
     * and beforeInsert and beforeUpdate hooks.
     */
    protected function beforeSave() { }

    /**
     * Hook called after performing inserts but before calling changesApplied and afterSave
     * It is called only on success
     */
    protected function afterInsert() { }

    /**
     * Hook called after performing updates but before calling changesApplied and afterSave
     * It is called only if some rows have been affected
     */
    protected function afterUpdate() { }

    /**
     * Hook called after performing the changes AND after calling changesApplied
     * It is called only on success, EVEN IF NO ROWS have been affected
     */
    protected function afterSave() { }

    /**
     * Hook called after performing the changes AND after calling afterSave.
     * It is called always, even if no rows have been affected
     */
    protected function finallyAfterSave() { }


    public function destroy()
    {
        $this->beforeDestroy();

        $deleted = false;
        if ($this->id && (!method_exists($this, 'isDestroyed') || !$this->isDestroyed())) {
            $this->beforeDelete();
            if ($deleted = (bool) static::delete(static::getTableName(), ['id=?' => $this->id])) {
                $this->afterDelete();
            }

            if (method_exists($this, 'setDestroyed')) {
                $this->setDestroyed($deleted ?: 0 === $deleted);
            }
        }

        if ($deleted) {
            $this->afterDestroy();
        }

        $this->finallyAfterDelete();
        return $deleted;
    }

    protected function beforeDestroy() { }

    protected function beforeDelete() { }

    protected function afterDelete() { }

    protected function afterDestroy() { }

    protected function finallyAfterDelete() { }

    /**
     * Eager Load $resources on $objects
     *
     * @usage: Persistence::eagerLoad([$object], ['including' => ['items', 'images'],  'excluding' => ['user']]);
     *
     * WARNING: if the eager loaded resources can be present in more then one $object
     * eg: if we have Items that belongs to an Order is the Order that have to eager Load the Items
     *
     * @param array $objects
     * @param array $opts
     *
     * @throws NoPropertyException
     * @throws \Exception
     */
    public static function eagerLoad($objects, $opts = [])
    {
        $including = [];
        $excluding = [];
        $using = [];
        $scopes = [];
        $exclusiveScopes = [];
        $fields = ['*'];
        $orderBy = [];
        extract($opts, EXTR_IF_EXISTS);

        $objects = array_filter($objects);
        $fields = array_flatten([$fields]);
        $objIds = array_filter(Arrays::extractProperty($objects, 'id'));
        $including = array_unique($including ?: []);
        $excluding = array_unique($excluding ?: []);

        if (!count($objects)) {
            return;
        }

        $abstractObject = current($objects);

        if ($including) {
            foreach ($including as $fieldRule) {
                // Extract the property name from the field rules
                $nestedFieldRules = [];
                $targetProp = '';
                if (false !== stripos($fieldRule, '/')) {
                    $nestedFieldRules = explode('/', $fieldRule);
                    $targetProp = array_shift($nestedFieldRules);
                } else {
                    $targetProp = $fieldRule;
                }

                if (!is_object($abstractObject) || !($abstractTarget = $abstractObject->$targetProp)) {
                    continue;
                }

                if ($abstractTarget->serializeAsProxy) {
                    continue;
                }

                $targetClass = $abstractTarget->getClassType(['using' => $abstractObject]);
                // FIXME: Find a way to decide when to use the "using", if in the
                // source property or the target property.
                // Later remove the $targetId assignation seen below.
                $sourceId = @$using[$targetProp]
                    ?: self::getColumnName(static::getClassName()) . '_id';
                $targetId = self::getColumnName($targetProp) . '_id';

                // To check on which way of the association the FK is sitting on
                // it will follow RoR conventions.
                // - The "has one" says that the fk field should be in "source" object
                // - The "belongs to" says that the fk field should be in "target" object
                // - The "polymorphic" is like the belongs to and also includes the type of the
                // source object as a field
                // - The "pluggable" is also like the belongs to but it also indicates the
                // source property name
                // - The "has many" is like a belongs to but with multiple entries
                $isHasOne = $isBelongsTo = $isPolymorphic = false;

                if (!($isHasOne = property_exists(get_called_class(), $targetId))
                    && !($isPolymorphic = is_a($targetClass, 'Vreasy\Polymorphic', true))
                    && !($isBelongsTo = property_exists($targetClass, $sourceId))
                ) {
                    // FIXME: Find a way to decide when to use the "using", if in the
                    // source property or the target property.
                    $targetId =  @$using[$targetProp]
                        ?: self::getColumnName($targetProp) . '_id';
                    if (!($isHasOne = property_exists(get_called_class(), $targetId))
                        && !($isPolymorphic = is_a($targetClass, 'Vreasy\Polymorphic', true))
                        && !($isBelongsTo = property_exists($targetClass, $sourceId))
                    ) {
                        throw new NoPropertyException(
                            sprintf(
                                'Property `%4$s` not found in %1$s to load %2$s\'s `%3$s`',
                                $targetClass,
                                get_called_class(),
                                $targetProp,
                                $sourceId
                            )
                        );
                    }
                }

                $whereParams = [];
                if ($isHasOne) {
                    if (count($ids = Arrays::extractProperty($objects, $targetId))) {
                        $whereParams = ['id' => $ids];
                    }
                } elseif ($isPolymorphic) {
                    $whereParams = [
                        $targetClass::getPropertyForId()->getName() => $objIds,
                        $targetClass::getPropertyForType()->getName() => Inflector::tableize(
                            static::getClassName()
                        )
                    ];

                    // Since the fk field is in the target object, it is of the "belongs to" type
                    $isBelongsTo = true;
                    $sourceId = $targetClass::getPropertyForId()->getName();
                    if (is_a($targetClass, 'Vreasy\FieldPluggable', true)) {
                        $whereParams += [
                            $targetClass::getPropertyForTargetField()->getName() => $targetProp,
                        ];
                    }
                } elseif ($isBelongsTo) {
                    $whereParams = [$sourceId => $objIds];
                }

                if (!array_filter($whereParams)) {
                    continue;
                }

                if ($isBelongsTo && !$objIds) {
                    // Skip the eager load for the current property since there are no
                    // ids to work with. But only for belongs to association types,
                    // the has one type uses the id of the other end of the relationship.
                    continue;
                }

                $resourcesLoaded = $targetClass::where(
                    $whereParams,
                    [
                        'orderBy' => array_filter(array_merge(
                            array_keys($whereParams) ?: [],
                            @$orderBy[$targetProp] ?: []
                        )),
                        'fields' => Builder::extractFieldsRulesFor($fields, $targetProp),
                        'scopes' => @$scopes[$targetProp],
                        'exclusiveScopes' => @$exclusiveScopes[$targetProp],
                    ]
                );
                if (method_exists($targetClass, 'eagerLoad')) {
                    $innerOpts = [
                        'including' => Builder::extractFieldsRulesFor($including, $targetProp),
                        'scopes' => [],
                        'exclusiveScopes' => []
                    ];
                    $optWithInnerRules = [
                        'scopes' => $scopes ?: [],
                        'exclusiveScopes' => $exclusiveScopes ?: [],
                    ];
                    foreach ($optWithInnerRules as $name => $opt) {
                        $innerScopeRules = Builder::extractFieldsRulesFor(
                            array_keys($opt),
                            $targetProp
                        );
                        foreach ($innerScopeRules as $resultField) {
                            if (isset($opt[$targetProp.'/'.$resultField])) {
                                $innerOpts[$name] += [$resultField => $opt[$targetProp.'/'.$resultField]];
                            }
                        }
                    }

                    $targetClass::eagerLoad(
                        $resourcesLoaded,
                        array_filter($innerOpts)
                    );
                }

                $tempObjects = $objects;
                // Sort the objects by the id to be used when associating
                // the eager loaded resource, this way it can skip iterations
                usort($objects, function ($a, $b) use ($targetId, $isHasOne) {
                    return $isHasOne
                        ? (int) $a->$targetId - (int) $b->$targetId
                        : (int) $a->id - (int) $b->id;
                });

                if ($abstractTarget instanceof One) {
                    foreach ($objects as $object) {
                        $object->$targetProp->clear();
                    }

                    foreach ($resourcesLoaded as $resource) {
                        foreach ($objects as $idx => $object) {
                            $lastLoadedId = null;
                            if (($isHasOne && $object->$targetId == $resource->id)
                                || ($isBelongsTo && $resource->$sourceId == $object->id)
                            ) {
                                $object->$targetProp = $resource;
                                $lastLoadedId = $isHasOne ? $resource->id : $object->id;
                                unset($objects[$idx]);
                            }

                            if ($lastLoadedId
                                && (($isHasOne && $lastLoadedId != $object->$targetId)
                                    || ($isBelongsTo && $lastLoadedId != $resource->$sourceId))
                            ) {
                                break;
                            }
                        }
                    }
                    $objects = $tempObjects;

                } elseif ($abstractTarget instanceof Many) {
                    foreach ($objects as $object) {
                        //clear targetProp to store the new value and not append to old one
                        $object->$targetProp->clear();
                        foreach ($resourcesLoaded as $idx => $resource) {
                            if ($isBelongsTo && $resource->$sourceId == $object->id) {
                                $object->$targetProp->append($resource);
                                unset($resourcesLoaded[$idx]);
                            } else {
                                break;
                            }
                        }
                    }
                    $objects = $tempObjects;
                } else {
                    throw new \InvalidArgumentException(
                        "$targetProp should be an instance of One or Many"
                    );
                }
            }
        }

        if ($excluding) {
            throw new NotImplementedException('Excluding an association is not implemented');
        }
    }

    // TODO: Remove this method, it is not clear what the use case is...
    protected static function getObjectsWithEmptyAssociation($objects, $property)
    {
        $emptyAssociations = [];
        foreach ($objects as $object) {
            if ($object->$property->isEmpty()) {
                $emptyAssociations[$object->id] = $object;
            }
        }

        return $emptyAssociations;
    }

    public static function getAssociations($objects, $property)
    {
        $associations = [];
        foreach ($objects as $object) {
            if ($object->$property->isPresent()) {
                $associations[$object->id] = $object->$property->getAssociation();
            }
        }

        return $associations;
    }

    protected static function getUnionClause($where, $builtScopes, $builtUnions, $unionsValues, $opts = [])
    {
        $select = '';
        extract($opts, EXTR_IF_EXISTS);
        if ($usersWhere = @$builtScopes['user']) {
            // FIXME: Since the ONLY place where we need to use unions is in a user scope
            // we get rid of the subquery that it adds before building the union clause
            $where = str_ireplace('AND '.$usersWhere, '', $where);
            $where = str_ireplace($usersWhere, '', $where);
            $where = str_ireplace('AND () AND', '', $where);
            $where = str_ireplace('() AND', '', $where);
            $where = str_ireplace('( AND', '(', $where);
            $where = str_ireplace('AND )', ')', $where);
        }
        return implode(' ', array_map(
            function($u) use($select, $where) {
                // Identify the case when the where clause is empty, and instead of appending
                // and `AND` clause, do it without the `AND`
                return "UNION $select "
                .(trim(str_ireplace('where', '', $where)) ? "$where AND ($u)" : "WHERE ($u)");
            },
            $builtUnions
        ));
    }

    protected static function buildUnions($opts = [])
    {
        $alias = 'e';
        $unions = [];
        extract($opts, EXTR_IF_EXISTS);

        $unionWheres = [];
        $values = [];
        if ($unions) {
            foreach ($unions as $idx => $unionParams) {
                list($w, $v) = Builder::expandWhere(
                    $unionParams,
                    ['prefix' => "$alias."]
                );
                $valueKeys = array_keys($v);
                $prefix = 'u'.$idx.'_';
                $w = str_replace(
                    array_map(function($i){ return ":$i";}, $valueKeys),
                    array_map(function($i) use($prefix) { return ":$prefix$i";}, $valueKeys),
                    $w
                );

                foreach ($v as $oldKey => $value) {
                    $v[$prefix.$oldKey] = $value;
                    unset($v[$oldKey]);
                }

                $where[] = trim($w);
                $values += (array) $v;

                $unionWheres[] = $w;
            }
        }
        return [$unionWheres, $values];
    }

    protected static function getLimitClause($opts = [])
    {
        $limit = 0;
        $start = 0;
        extract($opts, EXTR_IF_EXISTS);

        return $limit ? "LIMIT ".($start ? "$start, " : '')."$limit" : '';
    }

    protected static function getOrderByClause($opts = [])
    {
        $orderBy = [];
        $rawOrderBy = [];
        $orderDirection = [];
        extract($opts, EXTR_IF_EXISTS);

        foreach ($orderBy as $i => $key) {
            // Sanitize column name to avoid injection by closing the back-tick quote
            $key = str_ireplace('`', '', $key);
            $direction = isset($orderDirection[$i])
                ? strtoupper($orderDirection[$i])
                : (@$direction ?: 'ASC');
            if ($prefixAndKey = Builder::extractPrefixAndKey($key)) {
                list($prefix, $key) = $prefixAndKey;
                $orderBy[$i] = "$prefix`$key` $direction";
            } else {
                $orderBy[$i] = "`$key` $direction";
            }
        }

        foreach ($rawOrderBy as $i => $key) {
            $direction = @$orderDirection[$i] ?: 'ASC';
            $orderBy[$i] = "$key $direction";
        }
        return ($orderBy = implode(', ', $orderBy)) ? ' ORDER BY '.$orderBy : '';
    }

    /**
     * Gets the name of the class without the namespace
     * @return string The class name without the namespace
     */
    public static function getClassName()
    {
        if (!static::getPersistenceClassName()
            && false !== preg_match('@[\\\]{0,1}([\w]+)$@', get_called_class(), $m)
        ) {
            $n = false !== stripos($m[1], '_')
                // Remove "Worldhomes" prefix and name-space from old legacy classes names with `_`
                // leaving just the last word after the last underscore
                ? substr(strrchr(@$m[1], '_'), 1)
                : @$m[1];
            static::setPersistenceClassName($n);
        }
        return static::getPersistenceClassName();
    }

    public static function getPersistenceClassName()
    {
        return method_exists('parent', 'getPersistenceClassName')
            ? parent::getPersistenceClassName()
            : self::$persistenceClassName;
    }

    public static function setPersistenceClassName($value)
    {
        return method_exists('parent', 'setPersistenceClassName')
            ? parent::setPersistenceClassName($value)
            : (self::$persistenceClassName = $value);
    }

    /**
     * Gets the table name to be used when persisting the object
     *
     * When the table name does not derivates directly from the class name,
     * this method should be ovewritten.
     *
     * @return string The table name for this class
     */
    public static function getTableName()
    {
        return Inflector::pluralize(Inflector::tableize(static::getClassName()));
    }

    private static function getColumnName($name)
    {
        return Inflector::singularize(Inflector::tableize($name));
    }
}
