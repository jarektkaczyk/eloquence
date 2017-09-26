<?php

namespace Sofa\Eloquence\Metable;

use BadMethodCallException;

/**
 * This class provides instance scope for the closures
 * so they can be rebound later onto the acutal model.
 */
class Hooks
{
    /**
     * Register hook on getAttribute method.
     *
     * @return \Closure
     */
    public function getAttribute()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if (is_null($value)) {
                $value = $this->getMeta($key);
            }

            return $next($value, $args);
        };
    }

    /**
     * Register hook on setAttribute method.
     *
     * @return \Closure
     */
    public function setAttribute()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if (!$this->hasColumn($key) && $this->allowsMeta($key) && !$this->hasSetMutator($key)) {
                return $this->setMeta($key, $value);
            }

            return $next($value, $args);
        };
    }

    /**
     * Register hook on toArray method.
     *
     * @return \Closure
     */
    public function toArray()
    {
        return function ($next, $attributes) {
            unset($attributes['meta_attributes'], $attributes['metaAttributes']);

            $attributes = array_merge($attributes, $this->getMetaAttributesArray());

            return $next($attributes);
        };
    }

    /**
     * Register hook on replicate method.
     *
     * @return \Closure
     */
    public function replicate()
    {
        return function ($next, $copy, $args) {
            $metaAttributes = $args->get('original')
                                    ->getMetaAttributes()
                                    ->replicate($args->get('except'));

            $copy->setRelation('metaAttributes', $metaAttributes);

            return $next($copy, $args);
        };
    }

    /**
     * Register hook on save method.
     *
     * @return \Closure
     */
    public function save()
    {
        return function ($next, $value, $args) {
            $this->saveMeta();

            return $next($value, $args);
        };
    }

    /**
     * Register hook on isset call.
     *
     * @return \Closure
     */
    public function __issetHook()
    {
        return function ($next, $isset, $args) {
            $key = $args->get('key');

            if (!$isset) {
                $isset = (bool) $this->hasMeta($key);
            }

            return $next($isset, $args);
        };
    }

    /**
     * Register hook on unset call.
     *
     * @return \Closure
     */
    public function __unsetHook()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if ($this->hasMeta($key)) {
                return $this->setMeta($key, null);
            }

            return $next($value, $args);
        };
    }

    /**
     * Register hook on queryHook method.
     *
     * @return \Closure
     */
    public function queryHook()
    {
        return function ($next, $query, $bag) {
            $method = $bag->get('method');
            $args   = $bag->get('args');
            $column = $args->get('column');

            if (!$this->hasColumn($column) && $this->allowsMeta($column) && $this->isMetaQueryable($method)) {
                return call_user_func_array([$this, 'metaQuery'], [$query, $method, $args]);
            }

            if (in_array($method, ['select', 'addSelect'])) {
                call_user_func_array([$this, 'metaSelect'], [$query, $args]);
            }

            return $next($query, $bag);
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
