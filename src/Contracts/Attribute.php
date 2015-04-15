<?php namespace Sofa\Eloquence\Contracts;

interface Attribute
{
    /**
     * Create new AttributeBag.
     *
     * @param  array  $models
     * @return \Sofa\Eloquence\Metable\AttributeBag
     */
    public function newCollection(array $models = []);

    /**
     * Get the meta attribute value.
     *
     * @return mixed
     */
    public function getValue();

    /**
     * Get the meta attribute key.
     *
     * @return string
     */
    public function getKey();

    /**
     * Set value of the meta attribute.
     *
     * @param mixed $value
     *
     * @throws \Sofa\Eloquence\Exceptions\InvalidTypeException
     */
    public function setValue($value);

    /**
     * Determine whether a get mutator exists for the value type.
     *
     * @return boolean
     */
    public function hasMetaGetMutator();

    /**
     * Determine whether a set mutator exists for the value type.
     *
     * @param  mixed   $value
     * @return boolean
     */
    public function hasMetaSetMutator($value);

    /**
     * Handle casting value to string.
     *
     * @return string
     */
    public function castToString();
}
