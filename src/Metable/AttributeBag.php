<?php namespace Sofa\Eloquence\Metable;

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
        $this->loadAndIndex($attributes);
    }

    /**
     * Add or update attribute.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function set($key, $value = null)
    {
        if ($key instanceof Attribute) {
            $value = $key->getValue();
            $key   = $key->getKey();
        }

        if ($this->has($key)) {
            $this->update($key, $value);
        } else {
            $this[$key] = $this->newAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Add or update attribute.
     *
     * @param \Sofa\Eloquence\Metable\Attribute  $item
     *
     * @throws \InvalidArgumentException
     */
    public function add($item)
    {
        $this->validate($item);

        return $this->set($item);
    }

    protected function validate($item)
    {
        // if ($item instanceof Attribute) {
        //     if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $attribute->getKey())) {
        //         throw new InvalidArgumentException("Provided key [{$key}] is not valid variable name.");
        //     }
        // }

        if ($item instanceof Attribute) {
            return true;
        }

        $type = is_object($item) ? get_class($item) : gettype($item);

        $class = Attribute::class;

        throw new InvalidArgumentException(
            "Attribute must be an instance of [{$class}]. [{$type}] given."
        );
    }

    /**
     * Update existing attribute.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return $this
     */
    protected function update($key, $value)
    {
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
     * Use attributes key as the collection index.
     *
     * @param  array $items
     * @return void
     */
    protected function loadAndIndex($items)
    {
        $retriever = $this->valueRetriever('key');

        $attributes = [];

        foreach ($items as $item) {
            if (!isset($item->key) || !isset($item->value)) {
                throw new InvalidArgumentException("Attribute must have key and value.");
            }

            $attributes[$retriever($item)] = $item;
        }

        $this->items = $attributes;
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

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

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
}
