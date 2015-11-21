<?php

namespace Sofa\Eloquence\Metable;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Collection;
use Sofa\Eloquence\Contracts\AttributeBag as AttributeBagContract;

class AttributeBag extends Collection implements AttributeBagContract
{
    /**
     * Create new AttributeBag.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        foreach ($attributes as $attribute) {
            $this->add($attribute);
        }
    }

    /**
     * Add or update attribute.
     *
     * @param  \Sofa\Eloquence\Metable\Attribute|string $key
     * @param  mixed $value
     * @return $this
     */
    public function set($key, $value = null)
    {
        if ($key instanceof Attribute) {
            return $this->setInstance($key);
        }

        if ($this->has($key)) {
            $this->update($key, $value);
        } else {
            $this->items[$key] = $this->newAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Set attribute.
     *
     * @param \Sofa\Eloquence\Metable\Attribute $attribute
     */
    protected function setInstance(Attribute $attribute)
    {
        if ($this->has($attribute->getMetaKey())) {
            $this->update($attribute);
        } else {
            $this->items[$attribute->getMetaKey()] = $attribute;
        }

        return $this;
    }

    /**
     * Set attribute.
     *
     * @param \Sofa\Eloquence\Metable\Attribute $attribute
     */
    public function add($attribute)
    {
        return $this->addInstance($attribute);
    }

    /**
     * Set attribute.
     *
     * @param \Sofa\Eloquence\Metable\Attribute $attribute
     */
    protected function addInstance(Attribute $attribute)
    {
        return $this->set($attribute);
    }

    /**
     * Update existing attribute.
     *
     * @param  \Sofa\Eloquence\Metable\Attribute|string $key
     * @param  mixed  $value
     * @return $this
     */
    protected function update($key, $value = null)
    {
        if ($key instanceof Attribute) {
            $value = $key->getValue();
            $key = $key->getMetaKey();
        }

        $this->get($key)->setValue($value);

        return $this;
    }

    /**
     * New attribute instance.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return \Sofa\Eloquence\Metable\Attribute
     */
    protected function newAttribute($key, $value)
    {
        return new Attribute($key, $value);
    }

    /**
     * Get attribute value.
     *
     * @param  string $key
     * @return mixed
     */
    public function getValue($key)
    {
        if ($attribute = $this->get($key)) {
            return $attribute->getValue();
        }
    }

    /**
     * Get collection as key-value array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_filter(array_map(function ($attribute) {
            return $attribute->getValue();
        }, $this->items));
    }

    /**
     * Unset attribute.
     *
     * @param  string $key
     * @return $this
     */
    public function forget($key)
    {
        if ($attribute = $this->get($key)) {
            $attribute->setValue(null);
        }

        return $this;
    }

    /**
     * Set attribute.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Set attribute to null.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->forget($key);
    }

    /**
     * Handle dynamic properties.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Handle dynamic properties.
     *
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getValue($key);
    }

    /**
     * Handle isset calls.
     *
     * @param  string  $key
     * @return boolean
     */
    public function __isset($key)
    {
        return (bool) $this->get($key);
    }

    /**
     * Handle unset calls.
     *
     * @param  string $key
     * @return void
     */
    public function __unset($key)
    {
        $this->forget($key);
    }

    /**
     * Create copy of the attribute bag.
     *
     * @return static
     */
    public function replicate($except = null)
    {
        $except = $except ? array_combine($except, $except) : [];

        $attributes = [];

        foreach (array_diff_key($this->items, $except) as $attribute) {
            $attributes[] = $attribute->replicate();
        }

        return new static($attributes);
    }
}
