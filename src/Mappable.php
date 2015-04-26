<?php namespace Sofa\Eloquence;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @property array $maps
 */
trait Mappable
{
    /**
     * Related mapped objects to save along with the mappable instance.
     *
     * @var array
     */
    protected $targetsToSave = [];

    /**
     * Register hooks for the trait.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public static function bootMappable()
    {
        foreach ([
                'getAttribute',
                'setAttribute',
                'save',
                '__isset',
                '__unset',
                'queryHook',
            ] as $method) {
            static::hook($method, "{$method}Mappable");
        }
    }

    /**
     * Register hook on customWhere method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function queryHookMappable()
    {
        return function ($next, $query, $bag) {
            $method = $bag->get('method');
            $args   = $bag->get('args');
            $column = $args->get('column');

            if ($this->hasMapping($column)) {
                return call_user_func_array([$this, 'mappedQuery'], [$query, $method, $args]);
            }

            return $next($query, $bag);
        };
    }

    /**
     * Custom query handler for querying mapped attributes.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @return mixed
     */
    protected function mappedQuery(Builder $query, $method, ArgumentBag $args)
    {
        $mapping = $this->getMappingForAttribute($args->get('column'));

        if ($this->nestedMapping($mapping)) {
            return $this->mappedHasQuery($query, $method, $args, $mapping);
        }

        $args->set('column', $mapping);

        return $query->callParent($method, $args->all());

    }

    /**
     * Add whereHas subquery on the mapped attribute relation.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @param  string $mapping
     * @return \Sofa\Eloquence\Builder
     */
    protected function mappedHasQuery(Builder $query, $method, ArgumentBag $args, $mapping)
    {
        $boolean = $this->getMappedBoolean($args);

        $operator = $this->getMappedOperator($method, $args);

        list($target, $column) = $this->parseMapping($mapping);

        $args->set('column', $column);

        return $query
            ->has($target, $operator, 1, $boolean, $this->getMappedWhereConstraint($method, $args))
            ->with($target);
    }

    /**
     * Get the relation constraint closure.
     *
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @return \Closure
     */
    protected function getMappedWhereConstraint($method, ArgumentBag $args)
    {
        return function ($query) use ($method, $args) {
            call_user_func_array([$query, $method], $args->all());
        };
    }

    /**
     * Get boolean called on the original method and set it to default.
     *
     * @param  \Sofa\EloquenceArgumentBag $args
     * @return string
     */
    protected function getMappedBoolean(ArgumentBag $args)
    {
        $boolean = $args->get('boolean');

        $args->set('boolean', 'and');

        return $boolean;
    }

    /**
     * Determine the operator for count relation query and set 'not' appropriately.
     *
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @return string
     */
    protected function getMappedOperator($method, ArgumentBag $args)
    {
        if ($not = $args->get('not')) {
            $args->set('not', false);
        }

        if ($null = $this->isWhereNull($method, $args)) {
            $args->set('not', true);
        }

        return ($not xor $null) ? '<' : '>=';
    }

    /**
     * Determine whether where should be treated as whereNull.
     *
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @return boolean
     */
    protected function isWhereNull($method, $args)
    {
        return $method === 'whereNull' || $method === 'where' && $this->isWhereNullByArgs($args);
    }

    /**
     * Determine whether where is a whereNull by the arguments passed to where method.
     *
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @return boolean
     */
    protected function isWhereNullByArgs(ArgumentBag $args)
    {
        return is_null($args->get('operator'))
            || is_null($args->get('value')) && !in_array($args->get('operator'), ['<>', '!=']);
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
     * Determine whether the mapping points to relation.
     *
     * @param  string $mapping
     * @return boolean
     */
    protected function nestedMapping($mapping)
    {
        return strpos($mapping, '.') !== false;
    }

    /**
     * Get the target relation and column from the mapping.
     *
     * @param  string $mapping
     * @return array
     */
    protected function parseMapping($mapping)
    {
        $segments = explode('.', $mapping);

        $column = array_pop($segments);

        $target = implode('.', $segments);

        return [$target, $column];
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
     * Register hook on getAttribute method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function getAttributeMappable()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if ($this->hasMapping($key)) {
                $value = $this->mapAttribute($key);
            }

            return $next($value, $args);
        };
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
            if (!$target) {
                return;
            }

            $target = $target->{$segment};
        }

        return $target;
    }

    /**
     * Register hook on setAttribute method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function setAttributeMappable()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if ($this->hasMapping($key)) {
                return $this->setMappedAttribute($key, $value);
            }

            return $next($value, $args);
        };
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

        if ($target = $this->getTarget($this, $segments)) {
            $this->targetsToSave[] = $target;

            $target->{$attribute} = $value;
        }
    }

    /**
     * Register hook on save method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function saveMappable()
    {
        return function ($next, $value, $args) {
            $this->saveMapped();

            return $next($value, $args);
        };
    }

    /**
     * Save mapped relations.
     *
     * @return void
     */
    protected function saveMapped()
    {
        foreach (array_unique($this->targetsToSave) as $target) {
            $target->save();
        }

        $this->targetsToSave = [];
    }

    /**
     * Register hook on isset call.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function __issetMappable()
    {
        return function ($next, $isset, $args) {
            $key = $args->get('key');

            if (!$isset && $this->hasMapping($key)) {
                return (bool) $this->mapAttribute($key);
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
    public function __unsetMappable()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if ($this->hasMapping($key)) {
                return $this->forget($key);
            }

            return $next($value, $args);
        };
    }

    /**
     * Unset mapped attribute.
     *
     * @param  string $key
     * @return void
     */
    protected function forget($key)
    {
        $mapping = $this->getMappingForAttribute($key);

        list($target, $attribute) = $this->parseMapping($mapping);

        $target = $target ? $this->getTarget($this, explode('.', $target)) : $this;

        unset($target->{$attribute});
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritdoc
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
     * Get the array of attribute mappings.
     *
     * @return array
     */
    public function getMaps()
    {
        return (property_exists($this, 'maps')) ? $this->maps : [];
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
