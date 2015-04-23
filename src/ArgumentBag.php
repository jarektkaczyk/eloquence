<?php namespace Sofa\Eloquence;

use Sofa\Eloquence\Contracts\ArgumentBag as ArgumentBagContract;

class ArgumentBag implements ArgumentBagContract
{
    /**
     * Arguments being passed.
     *
     * @var array
     */
    protected $items;

    /**
     * Create new bag.
     *
     * @param array $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * @inheritdoc
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * @inheritdoc
     */
    public function first()
    {
        $items = $this->items;

        return $this->isEmpty() ? null : reset($items);
    }

    /**
     * @inheritdoc
     */
    public function last()
    {
        $items = array_reverse($this->items);

        return $this->isEmpty() ? null : reset($items);
    }

    /**
     * @inheritdoc
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    /**
     * Set value at given key.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        $this->items[$key] = $value;
    }

    /**
     * @inheritdoc
     */
    public function isEmpty()
    {
        return ! count($this->items);
    }
}
