<?php

/**
 * @package Sofa\Eloquence
 * @author Jarek Tkaczyk <jare@softonsofa.com>
 */

use Illuminate\Database\Eloquent\Model;

if (!function_exists('rules_for_update')) {
    /**
     * Adjust unique rules for update so it doesn't treat updated model's row as duplicate.
     *
     * @link  http://laravel.com/docs/5.0/validation#rule-unique
     *
     * @param  array $rules
     * @param  \Illuminate\Database\Eloquent\Model|integer|string $id
     * @param  string $primaryKey
     * @return array
     */
    function rules_for_update(array $rules, $id, $primaryKey = 'id')
    {
        if ($id instanceof Model) {
            list($primaryKey, $id) = [$id->getKeyName(), $id->getKey()];
        }

        // We want to update each unique rule so it ignores this model's row
        // during unique check in order to avoid faulty non-unique errors
        // in accordance to the linked Laravel Validator documentation.
        array_walk($rules, function (&$fieldRules, $field) use ($id, $primaryKey) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            array_walk($fieldRules, function (&$rule) use ($field, $id, $primaryKey) {
                if (strpos($rule, 'unique') === false) {
                    return;
                }

                list(,$argsString) = explode(':', $rule);

                $args = explode(',', $argsString);

                $args[1] = isset($args[1]) ? $args[1] : $field;
                $args[2] = $id;
                $args[3] = $primaryKey;

                $rule = 'unique:'.implode(',', $args);
            });
        });

        return $rules;
    }
}
