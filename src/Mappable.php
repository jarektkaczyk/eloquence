<?php namespace Sofa\Eloquence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array $maps
 */
trait Mappable
{

    /**
     * @codeCoverageIgnore
     *
     * @inheritdoc
     */
    public function getAttribute($key)
    {
        if ($this->hasMapping($key)) {
            return $this->mapAttribute($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritdoc
     */
    public function setAttribute($key, $value)
    {
        if ($this->hasMapping($key)) {
            return $this->setMappedAttribute($key, $value);
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     *
     * @codeCoverageIgnore
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    protected function mutateAttributeForArray($key, $value)
    {
        if ($this->hasMapping($key)) {
            $value = $this->mapAttribute($key);

            return $value instanceof Arrayable ? $value->toArray() : $value;
        }

        return parent::mutateAttributeForArray($key, $value);
    }

    /**
     * Determine whether a mapping exists for an attribute.
     *
     * @param  string $key
     * @return boolean
     */
    public function hasMapping($key)
    {
        return $this->hasExplicitMapping($key) || $this->hasImplicitMapping($key);
    }

    /**
     * Set value of a mapped attribute.
     *
     * @param string $key
     * @param mixed  $value
     */
    protected function setMappedAttribute($key, $value)
    {
        $segments = explode('.', $this->getMappingForAttribute($key));

        $attribute = array_pop($segments);

        $target = $this->getTarget($this, $segments);

        $target->{$attribute} = $value;
    }

    /**
     * Map an attribute to a value.
     *
     * @param  string $key
     * @return mixed
     */
    protected function mapAttribute($key)
    {
        $segments = explode('.', $this->getMappingForAttribute($key));

        return $this->getTarget($this, $segments);
    }

    /**
     * Get mapped value.
     *
     * @param  \Illuminate\Database\Eloquent\Model $target
     * @param  array  $segments
     * @return mixed
     */
    protected function getTarget($target, array $segments)
    {
        foreach ($segments as $segment) {
            if (! $target) {
                return;
            }

            $target = $target->{$segment};
        }

        return $target;
    }

    /**
     * Get the mapping key.
     *
     * @param  string $key
     * @return string|null
     */
    public function getMappingForAttribute($key)
    {
        if ($this->hasExplicitMapping($key)) {
            return $this->getExplicitMapping($key);
        }

        if ($this->hasImplicitMapping($key)) {
            return $this->getImplicitMapping($key);
        }
    }

    /**
     * Get the key for explicit mapping.
     *
     * @param  string $key
     * @return string
     */
    protected function getExplicitMapping($key)
    {
        return $this->maps[$key];
    }

    /**
     * Get the key for implicit mapping.
     *
     * @param  string $key
     * @return string|null
     */
    protected function getImplicitMapping($key)
    {
        foreach ($this->maps as $related => $mappings) {
            if (is_array($mappings) && in_array($key, $mappings)) {
                return "{$related}.{$key}";
            }
        }
    }

    /**
     * Determine whether an attribute has implicit mapping.
     *
     * @param  string $key
     * @return boolean
     */
    protected function hasImplicitMapping($key)
    {
        foreach ($this->getMaps() as $mapping) {
            if (is_array($mapping) && in_array($key, $mapping)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether an attribute has explicit mapping.
     *
     * @param  string $key
     * @return boolean
     */
    protected function hasExplicitMapping($key)
    {
        $mapped = $this->getMaps();

        return array_key_exists($key, $mapped) && is_string($mapped[$key]);
    }

    /**
     * Get the array of attribute mappings.
     *
     * @return array
     */
    public function getMaps()
    {
        return (isset($this->maps)) ? $this->maps : [];
    }

    /**
     * Set array of attribute mappings on the model.
     *
     * @codeCoverageIgnore
     *
     * @param  array $mappings
     * @return void
     */
    public function setMaps(array $mappings)
    {
        $this->maps = $mappings;
    }
}
