<?php

namespace Sofa\Eloquence\Mappable;

use BadMethodCallException;

/**
 * This class provides instance scope for the closures
 * so they can be rebound later onto the acutal model.
 */
class Hooks
{
    /**
     * Register hook on customWhere method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function queryHook()
    {
        return function ($next, $query, $bag) {
            $method = $bag->get('method');
            $args   = $bag->get('args');
            $column = $args->get('column');

            if ($this->hasMapping($column)) {
                return call_user_func_array([$this, 'mappedQuery'], [$query, $method, $args]);
            }

            if (in_array($method, ['select', 'addSelect'])) {
                call_user_func_array([$this, 'mappedSelect'], [$query, $args]);
            }

            return $next($query, $bag);
        };
    }

    public function isDirty()
    {
        return function ($next, $attributes = null, $bag) {
            if (is_array($attributes)) {
                $attributes = array_map(function ($attribute) {
                    return $this->getMappingForAttribute($attribute) ?: $attribute;
                }, $attributes);
            }
            return $next($attributes, $bag);
        };
    }

    /**
     * Register hook on save method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function save()
    {
        return function ($next, $value, $args) {
            $this->saveMapped();

            return $next($value, $args);
        };
    }

    /**
     * Register hook on isset call.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function __issetHook()
    {
        return function ($next, $isset, $args) {
            $key = $args->get('key');

            if (!$isset && $this->hasMapping($key)) {
                return (bool) $this->mapAttribute($key);
            }

            return $next($isset, $args);
        };
    }

    /**
     * Register hook on unset call.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function __unsetHook()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if ($this->hasMapping($key)) {
                return $this->forget($key);
            }

            return $next($value, $args);
        };
    }

    /**
     * Register hook on getAttribute method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function getAttribute()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if ($this->hasMapping($key)) {
                $value = $this->mapAttribute($key);
            }

            return $next($value, $args);
        };
    }

    /**
     * Register hook on setAttribute method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function setAttribute()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if ($this->hasMapping($key)) {
                return $this->setMappedAttribute($key, $value);
            }

            return $next($value, $args);
        };
    }

    public function __call($method, $params)
    {
        if (strpos($method, '__') === 0 && method_exists($this, $method.'Hook')) {
            return call_user_func_array([$this, $method.'Hook'], $params);
        }

        throw new BadMethodCallException("Method [{$method}] doesn't exist on this object.");
    }
}
