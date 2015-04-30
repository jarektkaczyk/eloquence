<?php namespace Sofa\Eloquence;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

        if ($this->relationMapping($mapping)) {
            return $this->mappedRelationQuery($query, $method, $args, $mapping);
        }

        $args->set('column', $mapping);

        return $query->callParent($method, $args->all());
    }

    /**
     * Handle querying relational mappings.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @param  string $mapping
     * @return mixed
     */
    protected function mappedRelationQuery($query, $method, ArgumentBag $args, $mapping)
    {
        list($target, $column) = $this->parseMapping($mapping);

        if (in_array($method, ['pluck', 'aggregate', 'orderBy', 'lists'])) {
            return $this->mappedJoinQuery($query, $method, $args, $target, $column);
        }

        return $this->mappedHasQuery($query, $method, $args, $target, $column);
    }

    /**
     * Join mapped table(s) in order to call given method.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @param  string $target
     * @param  string $column
     * @return mixed
     */
    protected function mappedJoinQuery($query, $method, ArgumentBag $args, $target, $column)
    {
        $table = $this->joinMapped($query, $target);

        // For aggregates we need the actual function name
        // so it can be called directly on the builder.
        $method = $args->get('function') ?: $method;

        return (in_array($method, ['orderBy', 'lists']))
            ? $this->{"{$method}Mapped"}($query, $args, $table, $column, $target)
            : $this->mappedSingleResult($query, $method, "{$table}.{$column}");
    }

    /**
     * Order query by mapped attribute.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @param  string $table
     * @param  string $column
     * @param  string $target
     * @return \Sofa\Eloquence\Builder
     */
    protected function orderByMapped(Builder $query, ArgumentBag $args, $table, $column, $target)
    {
        $query->with($target)->getQuery()->orderBy("{$table}.{$column}", $args->get('direction'));

        return $query;
    }

    /**
     * Get an array with the values of given mapped attribute.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @param  string $table
     * @param  string $column
     * @return array
     */
    protected function listsMapped(Builder $query, ArgumentBag $args, $table, $column)
    {
        $query->select("{$table}.{$column}");

        if (!is_null($args->get('key'))) {
            $this->selectListsKey($query, $args->get('key'));
        }

        $args->set('column', $column);

        return $query->callParent('lists', $args->all());
    }

    /**
     * Add select clause for key of the list array.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $key
     * @return \Sofa\Eloquence\Builder
     */
    protected function selectListsKey(Builder $query, $key)
    {
        if ($this->hasColumn($key)) {
            return $query->addSelect($this->getTable().'.'.$key);
        }

        return $query->addSelect($key);
    }

    /**
     * Join mapped table(s).
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $column
     * @return string
     */
    protected function joinMapped(Builder $query, $target)
    {
        $this->prefixColumnsForJoin($query);

        $parent = $this;

        foreach (explode('.', $target) as $segment) {
            list($table, $parent) = $this->joinSegment($query, $segment, $parent);
        }

        return $table;
    }

    /**
     * Join relation's table accordingly.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $segment
     * @param  \Illuminate\Database\Eloquent\Model $parent
     * @return array
     */
    protected function joinSegment(Builder $query, $segment, Model $parent)
    {
        $relation = $parent->{$segment}();
        $related  = $relation->getRelated();
        $table    = $related->getTable();

        // If the table has been already joined let's skip it. Otherwise we will left join
        // it in order to allow using some query methods on mapped columns. Polymorphic
        // relations require also additional constraints, so let's handle it as well.
        if (!$this->alreadyJoined($query, $table)) {
            list($fk, $pk) = $this->getJoinKeys($relation);

            $query->leftJoin($table, function ($join) use ($fk, $pk, $relation, $parent, $related) {
                $join->on($fk, '=', $pk);

                if ($relation instanceof MorphOne || $relation instanceof MorphTo) {
                    $morphClass = ($relation instanceof MorphOne)
                        ? $parent->getMorphClass()
                        : $related->getMorphClass();

                    $join->where($relation->getMorphType(), '=', $morphClass);
                }
            });
        }

        return [$table, $related];
    }

    /**
     * Determine whether given table has been already joined.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string  $table
     * @return boolean
     */
    protected function alreadyJoined(Builder $query, $table)
    {
        foreach ((array) $query->getQuery()->joins as $join) {
            if ($join->table == $table) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the keys from relation in order to join the table.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @return array
     *
     * @throws \LogicException
     */
    protected function getJoinKeys(Relation $relation)
    {
        if ($relation instanceof HasOne || $relation instanceof MorphOne) {
            return [$relation->getForeignKey(), $relation->getQualifiedParentKeyName()];
        }

        if ($relation instanceof BelongsTo && !$relation instanceof MorphTo) {
            return [$relation->getQualifiedForeignKey(), $relation->getQualifiedOtherKeyName()];
        }

        $class = get_class($relation);

        throw new LogicException(
            "Only HasOne, MorphOne and BelongsTo mappings can be queried. {$class} given."
        );
    }

    /**
     * Get single value result from the mapped attribute.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  string $qualifiedColumn
     * @return mixed
     */
    protected function mappedSingleResult(Builder $query, $method, $qualifiedColumn)
    {
        return $query->getQuery()->select("{$qualifiedColumn}")->{$method}("{$qualifiedColumn}");
    }

    /**
     * Add whereHas subquery on the mapped attribute relation.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @param  string $target
     * @param  string $column
     * @return \Sofa\Eloquence\Builder
     */
    protected function mappedHasQuery(Builder $query, $method, ArgumentBag $args, $target, $column)
    {
        $boolean = $this->getMappedBoolean($args);

        $operator = $this->getMappedOperator($method, $args);

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
    protected function relationMapping($mapping)
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
}
