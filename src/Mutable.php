<?php

namespace Sofa\Eloquence;

use Sofa\Eloquence\Mutable\Hooks;
use Sofa\Eloquence\Mutator\Mutator;

/**
 * @property array $setterMutators
 * @property array $getterMutators
 */
trait Mutable
{
    /**
     * Register hooks for the trait.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public static function bootMutable()
    {
        $hooks = new Hooks;

        foreach (['setAttribute', 'getAttribute', 'toArray'] as $method) {
            static::hook($method, $hooks->{$method}());
        }
    }

    /**
     * Mutate mutable attributes for array conversion.
     *
     * @param  array $attributes
     * @return array
     */
    protected function mutableAttributesToArray(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if ($this->hasGetterMutator($key)) {
                $attributes[$key] = $this->mutableMutate($key, $value, 'getter');
            }
        }

        return $attributes;
    }

    /**
     * Determine whether an attribute has getter mutators defined.
     *
     * @param  string  $key
     * @return boolean
     */
    public function hasGetterMutator($key)
    {
        return array_key_exists($key, $this->getMutators('getter'));
    }

    /**
     * Determine whether an attribute has setter mutators defined.
     *
     * @param  string  $key
     * @return boolean
     */
    public function hasSetterMutator($key)
    {
        return array_key_exists($key, $this->getMutators('setter'));
    }

    /**
     * Mutate the attribute.
     *
     * @param  string $key
     * @param  string $value
     * @param  string $dir
     * @return mixed
     */
    protected function mutableMutate($key, $value, $dir)
    {
        $mutators = $this->getMutatorsForAttribute($key, $dir);

        return static::$attributeMutator->mutate($value, $mutators);
    }

    /**
     * Get the mutators for an attribute.
     *
     * @param  string $key
     * @return string
     */
    protected function getMutatorsForAttribute($key, $dir)
    {
        return $this->getMutators($dir)[$key];
    }

    /**
     * Get the array of attribute mutators.
     *
     * @param  string $dir
     * @return array
     */
    public function getMutators($dir)
    {
        $property = ($dir === 'setter') ? 'setterMutators' : 'getterMutators';

        return (property_exists($this, $property)) ? $this->{$property} : [];
    }
}
