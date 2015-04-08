<?php namespace Sofa\Eloquence\Contracts;

interface ArgumentBag
{
    /**
     * Get array with all arguments.
     *
     * @return array
     */
    public function all();

    /**
     * Fetch first argument from the bag.
     *
     * @return mixed
     */
    public function first();

    /**
     * Fetch last argument from the bag.
     *
     * @return mixed
     */
    public function last();

    /**
     * Get argument with given key.
     *
     * @param  string|int $key
     * @param  mixed $default
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Determine whether the bag is empty.
     *
     * @return boolean
     */
    public function isEmpty();
}
