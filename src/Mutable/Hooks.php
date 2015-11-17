<?php

namespace Sofa\Eloquence\Mutable;

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

            if ($this->hasGetterMutator($key)) {
                $value = $this->mutableMutate($key, $value, 'getter');
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

            if ($this->hasSetterMutator($key)) {
                $value = $this->mutableMutate($key, $value, 'setter');
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
            $attributes = $this->mutableAttributesToArray($attributes);

            return $next($attributes);
        };
    }
}
