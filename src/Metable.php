<?php

namespace Sofa\Eloquence;

use Sofa\Eloquence\Metable\Hooks;
use Sofa\Eloquence\Metable\Attribute;
use Sofa\Eloquence\Metable\AttributeBag;
use Sofa\Hookable\Contracts\ArgumentBag;

/**
 * @property array $allowedMeta
 */
trait Metable
{
    /**
     * Query methods customizable by this trait.
     *
     * @var array
     */
    protected $metaQueryable = [
        'where', 'whereBetween', 'whereIn', 'whereNull',
        'whereDate', 'whereYear', 'whereMonth', 'whereDay',
        'orderBy', 'pluck', 'value', 'aggregate',
    ];

    /**
     * Register hooks for the trait.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public static function bootMetable()
    {
        $hooks = new Hooks;

        foreach ([
                'setAttribute',
                'getAttribute',
                'toArray',
                'replicate',
                'save',
                '__isset',
                '__unset',
                'queryHook',
            ] as $method) {
            static::hook($method, $hooks->{$method}());
        }
    }

    /**
     * Determine wheter method called on the query is customizable by this trait.
     *
     * @param  string  $method
     * @return boolean
     */
    protected function isMetaQueryable($method)
    {
        return in_array($method, $this->metaQueryable);
    }

    /**
     * Custom query handler for querying meta attributes.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @return mixed
     */
    protected function metaQuery(Builder $query, $method, ArgumentBag $args)
    {
        if (in_array($method, ['pluck', 'value', 'aggregate', 'orderBy', 'lists'])) {
            return $this->metaJoinQuery($query, $method, $args);
        }

        return $this->metaHasQuery($query, $method, $args);
    }

    /**
     * Adjust meta columns for select statement.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @return void
     */
    protected function metaSelect(Builder $query, ArgumentBag $args)
    {
        $columns = $args->get('columns');

        foreach ($columns as $key => $column) {
            list($column, $alias) = $this->extractColumnAlias($column);

            if ($this->hasColumn($column)) {
                $select = "{$this->getTable()}.{$column}";

                if ($column !== $alias) {
                    $select .= " as {$alias}";
                }

                $columns[$key] = $select;
            } elseif (is_string($column) && $column != '*' && strpos($column, '.') === false) {
                $table = $this->joinMeta($query, $column);

                $columns[$key] = "{$table}.meta_value as {$alias}";
            }
        }

        $args->set('columns', $columns);
    }

    /**
     * Join meta attributes table in order to call provided method.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @return mixed
     */
    protected function metaJoinQuery(Builder $query, $method, ArgumentBag $args)
    {
        $alias = $this->joinMeta($query, $args->get('column'));

        // For aggregates we need the actual function name
        // so it can be called directly on the builder.
        $method = $args->get('function') ?: $method;

        return (in_array($method, ['orderBy', 'lists', 'pluck']))
            ? $this->{"{$method}Meta"}($query, $args, $alias)
            : $this->metaSingleResult($query, $method, $alias);
    }

    /**
     * Order query by meta attribute.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @param  string $alias
     * @return \Sofa\Eloquence\Builder
     */
    protected function orderByMeta(Builder $query, $args, $alias)
    {
        $query->with('metaAttributes')->getQuery()->orderBy("{$alias}.meta_value", $args->get('direction'));

        return $query;
    }

    /**
     * Get an array with the values of given meta attribute.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @param  string $alias
     * @return array
     */
    protected function pluckMeta(Builder $query, ArgumentBag $args, $alias)
    {
        list($column, $key) = [$args->get('column'), $args->get('key')];

        $query->select("{$alias}.meta_value as {$column}");

        if (!is_null($key)) {
            $this->metaSelectListsKey($query, $key);
        }

        return $query->callParent('pluck', $args->all());
    }

    /**
     * Add select clause for key of the list array.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $key
     * @return \Sofa\Eloquence\Builder
     */
    protected function metaSelectListsKey(Builder $query, $key)
    {
        if (strpos($key, '.') !== false) {
            return $query->addSelect($key);
        } elseif ($this->hasColumn($key)) {
            return $query->addSelect($this->getTable() . '.' . $key);
        }

        $alias = $this->joinMeta($query, $key);

        return $query->addSelect("{$alias}.meta_value as {$key}");
    }

    /**
     * Get single value result from the meta attribute.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  string $alias
     * @return mixed
     */
    protected function metaSingleResult(Builder $query, $method, $alias)
    {
        return $query->getQuery()->select("{$alias}.meta_value")->{$method}("{$alias}.meta_value");
    }


    /**
     * Join meta attributes table.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $column
     * @return string
     */
    protected function joinMeta(Builder $query, $column)
    {
        $query->prefixColumnsForJoin();

        $alias = $this->generateMetaAlias();

        $table = (new Attribute)->getTable();

        $query->leftJoin("{$table} as {$alias}", function ($join) use ($alias, $column) {
            $join->on("{$alias}.metable_id", '=', $this->getQualifiedKeyName())
                ->where("{$alias}.metable_type", '=', $this->getMorphClass())
                ->where("{$alias}.meta_key", '=', $column);
        });

        return $alias;
    }

    /**
     * Generate unique alias for meta attributes table.
     *
     * @return string
     */
    protected function generateMetaAlias()
    {
        return md5(microtime(true)) . '_meta_alias';
    }

    /**
     * Add whereHas subquery on the meta attributes relation.
     *
     * @param  \Sofa\Eloquence\Builder $query
     * @param  string $method
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @return \Sofa\Eloquence\Builder
     */
    protected function metaHasQuery(Builder $query, $method, ArgumentBag $args)
    {
        $boolean = $this->getMetaBoolean($args);

        $operator = $this->getMetaOperator($method, $args);

        if (in_array($method, ['whereBetween', 'where'])) {
            $this->unbindNumerics($args);
        }

        return $query
            ->has('metaAttributes', $operator, 1, $boolean, $this->getMetaWhereConstraint($method, $args))
            ->with('metaAttributes');
    }

    /**
     * Get boolean called on the original method and set it to default.
     *
     * @param  \Sofa\EloquenceArgumentBag $args
     * @return string
     */
    protected function getMetaBoolean(ArgumentBag $args)
    {
        $boolean = $args->get('boolean');

        $args->set('boolean', 'and');

        return $boolean;
    }

    /**
     * Determine the operator for count relation query.
     *
     * @param  string $method
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @return string
     */
    protected function getMetaOperator($method, ArgumentBag $args)
    {
        if ($not = $args->get('not')) {
            $args->set('not', false);
        }

        return ($not ^ $this->isWhereNull($method, $args)) ? '<' : '>=';
    }

    /**
     * Integers and floats must be passed in raw form in order to avoid string
     * comparison, due to the fact that all meta values are stored as strings.
     *
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @return void
     */
    protected function unbindNumerics(ArgumentBag $args)
    {
        if (($value = $args->get('value')) && (is_int($value) || is_float($value))) {
            $args->set('value', $this->raw($value));
        } elseif ($values = $args->get('values')) {
            foreach ($values as $key => $value) {
                if (is_int($value) || is_float($value)) {
                    $values[$key] = $this->raw($value);
                }
            }

            $args->set('values', $values);
        }
    }

    /**
     * Get the relation constraint closure.
     *
     * @param  string $method
     * @param  \Sofa\Hookable\Contracts\ArgumentBag $args
     * @return \Closure
     */
    protected function getMetaWhereConstraint($method, ArgumentBag $args)
    {
        $column = $args->get('column');

        $args->set('column', 'meta_value');

        if ($method === 'whereBetween') {
            return $this->getMetaBetweenConstraint($column, $args->get('values'));
        }

        return function ($query) use ($column, $method, $args) {
            $query->where('meta_key', $column);

            if ($args->get('value') || $args->get('values')) {
                call_user_func_array([$query, $method], $args->all());
            }
        };
    }

    /**
     * Query Builder whereBetween override required to pass raw numeric values.
     *
     * @param  string $column
     * @param  array  $values
     * @return \Closure
     */
    protected function getMetaBetweenConstraint($column, array $values)
    {
        $min = $values[0];
        $max = $values[1];

        return function ($query) use ($column, $min, $max) {
            $query->where('meta_key', $column)
                ->where('meta_value', '>=', $min)
                ->where('meta_value', '<=', $max);
        };
    }

    /**
     * Save new or updated meta attributes and delete the ones that were unset.
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
     * @param  string $key
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
     * @param  string $key
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
     * Get meta attribute values by group.
     *
     * @param  string $key
     * @return mixed
     */
    public function getMetaByGroup($group)
    {
        return $this->getMetaAttributes()->getMetaByGroup($group);
    }
    /**
     * Set meta attribute.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function setMeta($key, $value, $group = null)
    {
        $this->getMetaAttributes()->set($key, $value, $group);
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
        return $this->morphMany('Sofa\Eloquence\Metable\Attribute', 'metable');
    }

    /**
     * Get meta attributes as collection.
     *
     * @return \Sofa\Eloquence\Metable\AttributeBag
     */
    public function getMetaAttributes()
    {
        $this->loadMetaAttributes();

        return $this->getRelation('metaAttributes');
    }

    /**
     * Accessor for metaAttributes property
     *
     * @return \Sofa\Eloquence\Metable\AttributeBag
     */
    public function getMetaAttributesAttribute()
    {
        return $this->getMetaAttributes();
    }

    /**
     * Get meta attributes as associative array.
     *
     * @return array
     */
    public function getMetaAttributesArray()
    {
        return $this->getMetaAttributes()->toArray();
    }

    /**
     * Load meta attributes relation.
     *
     * @return void
     */
    protected function loadMetaAttributes()
    {
        if (!array_key_exists('metaAttributes', $this->relations)) {
            $this->reloadMetaAttributes();
        }

        $attributes = $this->getRelation('metaAttributes');

        if (!$attributes instanceof AttributeBag) {
            $this->setRelation('metaAttributes', (new Attribute)->newBag($attributes->all()));
        }
    }

    /**
     * Reload meta attributes from db or set empty bag for newly created model.
     *
     * @return $this
     */
    protected function reloadMetaAttributes()
    {
        return ($this->exists)
            ? $this->load('metaAttributes')
            : $this->setRelation('metaAttributes', (new Attribute)->newBag());
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
}
