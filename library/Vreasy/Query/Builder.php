<?php
namespace Vreasy\Query;

class Builder
{
    public static function expandWhere(
        $params,
        $options = ['wildcard' => false, 'prefix' => '']
    ) {

        $or = 'or'; $and = 'and'; $parts = []; $where = ''; $values = [];
        $wildcard = false;
        $prefix = '';
        extract($options, EXTR_IF_EXISTS);

        // Filter out null params. To query for it use 'NULL' instead.
        $params = array_filter($params, function($i) { return !is_null($i); });

        // Normalize keys
        $params = array_change_key_case($params);

        $orsToJoin = [];
        if (isset($params[$or])) {
            $orsToJoin = array_merge($orsToJoin, $params[$or]);
        }

        $andsToJoin = [];
        if (isset($params[$and])) {
            $andsToJoin = array_merge($andsToJoin, $params[$and]);
        }

        // By default all params are joined with AND
        $withNoOperator = array_diff_key($params, array_flip([$or, $and]));
        $andsToJoin = array_merge($andsToJoin, $withNoOperator);

        // Build the where parts
        $operators = [$or => $orsToJoin, $and => $andsToJoin];
        foreach ($operators as $op => $items) {
            $toJoin = [];
            $defaultPrefix = $prefix;
            foreach ($items as $key => $value) {
                if ($prefixAndKey = static::extractPrefixAndKey($key)) {
                    list($prefix, $key) = $prefixAndKey;
                } else {
                    list($prefix, $key) = [$defaultPrefix, $key];
                }

                // When the value is an array join then all together withing an IN clause
                if (is_array($value)) {
                    if ($wildcard) {
                        $bindings = ''; $regexValues = [];
                        $valuesForRegexClause = array_filter(
                            $value,
                            function($v) {
                                // FIXME: It should peak ahead to find a trailing '*'
                                // so to match the first '*'. For now it will do the trick.
                                return preg_match('/^[\*].*[\*]$/', $v);
                            }
                        );
                        array_walk(
                            $valuesForRegexClause,
                            function($v, $k) use($key, &$bindings, &$regexValues) {
                                // Build the placeholders
                                $bindings = ":$key";
                                // Set the values and remove the leading and trailing asterisks
                                $regexValues["$key"][] = substr($v, 1, -1);
                            }
                        );
                        if ($regexValues) {
                            // Will be one bindin for a regex, but for the sake of consistency...
                            $toJoin[] = "$prefix`$key` REGEXP $bindings";
                            $regexValues["$key"] = implode('|', $regexValues["$key"]);
                            $values = array_merge($values, $regexValues);
                        }
                        $bindingOffset = count($valuesForRegexClause);
                    }

                    $bindings = []; $inValues = [];
                    $valuesForInClause = array_filter(
                        $value,
                        function($v) {
                            // FIXME: It should peak ahead to find a trailing '*'
                            // so to match the first '*'. For now it will do the trick.
                            return !preg_match('/^[\!\*].*[\*]{0,1}$/', $v);
                        }
                    );
                    array_walk(
                        $valuesForInClause,
                        function($v, $k) use($key, &$bindings, &$inValues) {
                            // Build the placeholders
                            $bindings[$k] = ":$key$k";
                            // Set the values
                            $inValues["$key$k"] = $v;
                        }
                    );
                    if ($inValues) {
                        $bindings = implode(', ', $bindings);
                        $toJoin[] = "$prefix`$key` IN ($bindings)";

                        $values = array_merge($values, $inValues);
                    }

                    $bindingOffset = count($valuesForInClause);
                    $bindings = []; $notinValues = [];
                    $valuesForNotInClause = array_filter(
                        $value,
                        function($v) {
                            return preg_match('/^\!.*$/', $v);
                        }
                    );
                    array_walk(
                        $valuesForNotInClause,
                        function($v, $k) use($key, &$bindings, &$notinValues, $bindingOffset) {
                            // Build the placeholders
                            $bindings[$k] = ":$key$k";
                            // Set the values
                            $notinValues["$key$k"] = str_replace('!', '', $v);
                        }
                    );
                    if ($notinValues) {
                        $bindings = implode(', ', $bindings);
                        $toJoin[] = "$prefix`$key` NOT IN ($bindings)";

                        $values = array_merge($values, $notinValues);
                    }
                }
                elseif(preg_match('/^\!NULL$/', $value)) {
                    $toJoin[] = "$prefix`$key` IS NOT NULL";
                }
                elseif(preg_match('/^NULL$/', $value)) {
                    $toJoin[] = "$prefix`$key` IS NULL";
                }
                else {
                    if ($wildcard && preg_match('/^\*.*\*$/', $value)) {
                        // Matches *value*
                        $value = str_replace('*', '%', $value);
                        $toJoin[] = "$prefix`$key` LIKE :$key";
                    } elseif (preg_match('/^\!.*$/', $value)) {
                        // Matches !value
                        $value = str_replace('!', '', $value);
                        $toJoin[] = "$prefix`$key` <> :$key";
                    } elseif (preg_match('/^\>=.*$/', $value)) {
                        // Matches >=value
                        $value = str_replace('>=', '', $value);
                        $toJoin[] = "$prefix`$key` >= :$key";
                    } elseif (preg_match('/^\>.*$/', $value)) {
                        // Matches >value
                        $value = str_replace('>', '', $value);
                        $toJoin[] = "$prefix`$key` > :$key";
                    } elseif (preg_match('/^\<=.*$/', $value)) {
                        // Matches <=value
                        $value = str_replace('<=', '', $value);
                        $toJoin[] = "$prefix`$key` <= :$key";
                    } elseif (preg_match('/^\<.*$/', $value)) {
                        // Matches <value
                        $value = str_replace('<', '', $value);
                        $toJoin[] = "$prefix`$key` < :$key";
                    } elseif (preg_match('/^\#\{.*\}$/msU', $value)) {
                        // Matches #{value}
                        $literal = preg_replace('/^\#\{(.*)\}$/msU', '$1', $value);
                        $toJoin[] = "($prefix`$key` $literal)";
                        // Do no add a value since its a literal
                        unset($value);
                    } elseif ($key == '#literal') {
                        // Matches a key with th name "#literal"
                        $literal = $value;
                        $toJoin[] = "($literal)";
                        // Do no add a value since its a literal
                        unset($value);
                    } else {
                        $toJoin[] = "$prefix`$key` = :$key";
                    }

                    if (isset($value)) {
                        $values[$key] = $value;
                    }
                }
            }
            $glue = strtoupper($op);
            $parts[$op] = implode(" $glue ", $toJoin);
        }

        if ($parts[$or] && $parts[$and]) {
            // When both operators are there wrap the OR statements with parenthesis
            $parts[$or] = "({$parts[$or]})";
            if (count($orsToJoin) == 1) {
                $parts[$and] = "({$parts[$and]})";
            }
        }
        $where = array_values($parts);
        $where = array_filter($where);
        if (count($orsToJoin) == 1) {
            // Support one single OR
            $where = implode(" OR ", $where);
        } else {
            $where = implode(" AND ", $where);
        }
        return [$where, $values];
    }

    public static function extractPrefixAndKey($key)
    {
        $prefixAndKey = explode('.', $key);
        if (count($prefixAndKey) > 1 && false === strpos(trim($key), ' ')) {
            // Add the trailing dot to the prefix
            $prefixAndKey[0] .= '.';
            return $prefixAndKey;
        }
    }

    /**
     * Expands a collection of field rules into columns to be used in a SQL query
     *
     * A Field rule could be a path into a nested association's field. For intance `'listing/id'`
     * is a rule to include the `id` of the `lising` association.
     *
     * It will only expand the rules that apply to the root level.
     *
     * @param string[] $fieldRules The collection of field rules as items to be expanded into columns.
     * @return string[] A collection of column names
     */
    public static function expandFieldsInToColumns($fieldRules)
    {
        $cols = [];
        foreach ($fieldRules as $rule) {
            if ($nested = explode('/', $rule)) {
                if (count($nested) == 1) {
                    // Expands root fields only for now
                    $cols[] = array_shift($nested);
                }
            }
        }
        return array_unique($cols);
    }


    /**
     * Expands a collection of field rules into field names.
     *
     * A Field rule could be a path into a nested association's field. For intance `'listing/id'`
     * is a rule to include the `id` of the `lising` association.
     *
     * It will expand all the "first-level" rules.
     *
     * @param string[] $fieldRules The collection of field rules as items to be expanded into columns.
     * @return string[] A collection of column names
     */
    public static function expandFieldsInToFields($fieldRules)
    {
        $cols = [];
        foreach ($fieldRules as $rule) {
            if ($nested = explode('/', $rule)) {
                // Expands root fields only for now
                $cols[] = array_shift($nested);
            }
        }
        return array_unique($cols);
    }

    /**
     * Extracts the rules for the given resource name for the given field rules collection
     *
     * @param string[] $fieldRules
     * @param string $resourceNames The collection of field rules where to search
     * @return string[] The new rules modified so it will apply to the given resource name
     */
    public static function extractFieldsRulesFor($fieldRules, $resourceNames)
    {
        $newRules = [];
        $resourceNames = is_array($resourceNames) ? $resourceNames : [$resourceNames];
        foreach ($fieldRules as $rule) {
            foreach ($resourceNames as $name) {
                if (0 === stripos($rule, "$name/")) {
                    $newRules[] = str_ireplace("$name/", '', $rule);
                }
            }
        }
        return array_unique($newRules);
    }
}
