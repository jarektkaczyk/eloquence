<?php namespace Sofa\Eloquence;

use Sofa\Eloquence\Metable\Attribute;
use Sofa\Eloquence\Builder;

/**
 * @property array $allowedMeta
 */
trait Metable
{
    /**
     * Register hooks for the trait.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public static function bootMetable()
    {
        foreach ([
                'setAttribute',
                'getAttribute',
                'toArray',
                'save',
                '__isset',
                '__unset',
                'customWhere',
            ] as $method) {
            static::hook($method, "{$method}Metable");
        }
    }

    /**
     * Register hook on getAttribute method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function getAttributeMetable()
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
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function setAttributeMetable()
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
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function toArrayMetable()
    {
        return function ($next, $attributes) {
            unset($attributes['meta_attributes'], $attributes['metaAttributes']);

            $attributes = array_merge($attributes, $this->getMetaAttributesArray());

            return $next($attributes);
        };
    }

    /**
     * Register hook on save method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function saveMetable()
    {
        return function ($next, $value, $args) {
            $this->saveMeta();

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
    public function __issetMetable()
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
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function __unsetMetable()
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
     * Register hook on customWhere method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function customWhereMetable()
    {
        return function ($next, $query, $args) {
            $key = $args->get('key');

            if (!$this->hasColumn($key) && $this->allowsMeta($key)) {
                return call_user_func_array([$this, 'whereMeta'], array_merge([$query], $args->all()));
            }

            return $next($query, $args);
        };
    }

    /**
     * Custom where clause for meta attributes.
     *
     * @return \Sofa\Eloquence\Builder
     */
    protected function whereMeta($query, $key, $operator, $value, $boolean)
    {
        // return $query->has('metaAttributes', '>=', 1, $boolean, function ($q) use ($key, $operator, $value) {
        return $query
            ->has('metaAttributes', '>=', 1, $boolean, $this->getMetaWhereConstraint($key, $operator, $value))
            ->with('metaAttributes');
    }

    /**
     * Get the relation constraint closure.
     *
     * @param  string  $key
     * @param  string  $operator
     * @param  string  $value
     * @return \Closure
     */
    protected function getMetaWhereConstraint($key, $operator = '=', $value = null)
    {
        return function ($query) use ($key, $operator, $value) {
            $query->where('key', $key);

            if ($value) {
                $query->where('value', $operator, $value);
            }
        };
    }

    /**
     * Query scope filtering by meta attribute key.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public function scopeHasMetaAttribute($query, $key, $boolean = 'and')
    {
        $query->has('metaAttributes', '>=', 1, $boolean, $this->getMetaWhereConstraint($key));
        // call_user_func_array([$this, 'scopeHasMetaAttributes'], [$query, (array) $key]);
    }

    /**
     * Query scope filtering by multiple meta attribute keys.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public function scopeHasMetaAttributes($query, array $keys, $boolean = 'and')
    {
        $query->where(function ($q) use ($keys, $boolean) {
            foreach ($keys as $key) {
                // $q->has('metaAttributes', '>=', 1, $boolean, function ($q) use ($key) {
                $q->has('metaAttributes', '>=', 1, $boolean, $this->getMetaWhereConstraint($key));
            }
        });
    }

    /**
     * Save new, updated meta attributes and delete the ones unset.
     *
     * @return void
     */
    protected function saveMeta()
    {
        foreach ($this->getMetaAttributes() as $attribute) {
            if (is_null($attribute->getValue())) {
                $attribute->delete();
            } else {
                $this->metaAttributes()->save($attribute);
            }
        }
    }

    /**
     * Determine whether meta attribute is allowed for the model.
     *
     * @param  string  $key
     * @return boolean
     */
    public function allowsMeta($key)
    {
        $allowed = $this->getAllowedMeta();

        return empty($allowed) || in_array($key, $allowed);
    }

    /**
     * Determine whether meta attribute exists on the model.
     *
     * @param  string  $key
     * @return boolean
     */
    public function hasMeta($key)
    {
        return array_key_exists($key, $this->getMetaAttributesArray());
    }

    /**
     * Get meta attribute value.
     *
     * @param  string $key
     * @return mixed
     */
    public function getMeta($key)
    {
        return $this->getMetaAttributes()->getValue($key);
    }

    /**
     * Set meta attribute.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function setMeta($key, $value)
    {
        $this->getMetaAttributes()->set($key, $value);
    }

    /**
     * Meta attributes relation.
     *
     * @codeCoverageIgnore
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function metaAttributes()
    {
        return $this->morphMany(Attribute::class, 'metable');
    }

    /**
     * Get meta attributes as collection.
     *
     * @return \Sofa\Eloquence\Metable\AttributeBags
     */
    public function getMetaAttributes()
    {
        $this->loadMetaAttributes();

        return $this->getRelation('metaAttributes');
    }

    /**
     * Get meta attributes as associative array.
     *
     * @return array
     */
    public function getMetaAttributesArray()
    {
        return array_filter($this->getMetaAttributes()->lists('value', 'key'));
    }

    /**
     * Load meta attributes relation.
     *
     * @param  boolean $reload
     * @return void
     */
    protected function loadMetaAttributes($reload = false)
    {
        if ($reload || !array_key_exists('metaAttributes', $this->relations)) {
            $this->load('metaAttributes');
        }
    }

    /**
     * Get allowed meta attributes array.
     *
     * @return array
     */
    public function getAllowedMeta()
    {
        return (property_exists($this, 'allowedMeta')) ? $this->allowedMeta : [];
    }

    /**
     * Set allowed meta attributes array.
     *
     * @param  array $attributes
     * @return void
     */
    public function setAllowedMeta(array $attributes)
    {
        $this->allowedMeta = $attributes;
    }
}
