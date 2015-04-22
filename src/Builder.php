<?php namespace Sofa\Eloquence;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    /**
     * All of the available clause operators.
     *
     * @var array
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'like binary', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
                'not similar to',
    ];

    /**
     * 'Not' based methods with custom handlers processed by magic __call.
     *
     * @var array
     */
    protected $notOverrides  = [
        'whereNotBetween', 'orWhereBetween', 'orWhereNotBetween',
        'whereNotIn', 'orWhereIn', 'orWhereNotIn'
    ];

    /**
     * Date based methods with custom handlers processed by magic __call.
     *
     * @var array
     */
    protected $dateOverrides = ['whereDate', 'whereYear', 'whereMonth', 'whereDay'];

    /**
     * Null based methods with custom handlers processed by magic __call.
     *
     * @var array
     */
    protected $nullOverrides = ['whereNotNull', 'orWhereNull', 'orWhereNotNull'];

    /**
     * Aggregate methods with custom handlers processed by magic __call.
     *
     * @var array
     */
    protected $aggregateOverrides  = ['min', 'max', 'sum', 'avg', 'count'];

    /**
     * Call base handler for where call.
     *
     * @param  string $method
     * @param  array  $args
     * @return $this
     */
    public function callParent($method, array $args)
    {
        return call_user_func_array("parent::{$method}", $args);
    }

    /**
     * Call custom handlers for where call.
     *
     * @param  string $method
     * @param  \Sofa\Eloquence\ArgumentBag $args
     * @return $this
     */
    protected function callHook($method, ArgumentBag $args)
    {
        // return $this->getModel()->queryHook($this, $method, $args);

        if ($this->hasHook($args->get('column'))) {
            return $this->getModel()->queryHook($this, $method, $args);
        }

        return $this->callParent($method, $args->all());
    }

    /**
     * Determine whether where call might have custom handler.
     *
     * @param  string  $column
     * @return boolean
     */
    protected function hasHook($column)
    {
        // If developer provided column prefixed with table name we will
        // not even try to map the column, since obviously the value
        // refers to the actual column name on the queried table.
        return is_string($column) && strpos($column, '.') === false;
    }

    /**
     * Pack arguments in ArgumentBag instance.
     *
     * @param  array  $args
     * @return \Sofa\Eloquence\ArgumentBag
     */
    protected function packArgs(array $args)
    {
        return new ArgumentBag($args);
    }

    /*
    |--------------------------------------------------------------------------
    | Query builder overrides
    |--------------------------------------------------------------------------
    */

    /**
     * Add where constraint to the query.
     *
     * @param  mixed  $column
     * @param  string $operator
     * @param  mixed  $value
     * @param  string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (!in_array(strtolower($operator), $this->operators, true)) {
            list($value, $operator) = [$operator, '='];
        }

        $bag = $this->packArgs(compact('column', 'operator', 'value', 'boolean'));

        return $this->callHook(__FUNCTION__, $bag);
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  boolean $not
     * @return $this
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column', 'values', 'boolean', 'not')));
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column', 'values', 'boolean', 'not')));
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereNull($column, $boolean = 'and', $not = false)
    {
        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column', 'boolean', 'not')));
    }

    /**
     * Add a date based (year, month, day) statement to the query.
     *
     * @param  string  $type
     * @param  string  $column
     * @param  string  $operator
     * @param  int     $value
     * @param  string  $boolean
     * @return $this
     */
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and')
    {
        return $this->callHook("where{$type}", $this->packArgs(compact('column', 'operator', 'value', 'boolean')));
    }

    /**
     * Add an exists clause to the query.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @param  bool     $not
     * @return $this
     */
    public function whereExists(\Closure $callback, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotExists' : 'Exists';

        $builder = $this->newQuery();

        call_user_func($callback, $builder);

        $query = $builder->getQuery();

        $this->query->wheres[] = compact('type', 'operator', 'query', 'boolean');

        $this->query->mergeBindings($query);

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column', 'direction')));
    }

    /**
     * Pluck a single column from the database.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column')));
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = array('*'))
    {
        $column = reset($columns);

        return $this->callHook(__FUNCTION__, $this->packArgs(compact('function', 'columns', 'column')));
    }

    /**
     * Get a new instance of the Eloquence query builder.
     *
     * @return \Sofa\Eloquence\Builder
     */
    public function newQuery()
    {
        return (new static($this->query->newQuery()))->setModel($this->model);
    }

    /**
     * Handle dynamic method calls.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->callOverride($method, $parameters);
    }

    /**
     * Handle dynamic method calls.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    protected function callOverride($method, $parameters)
    {
        $overrides = array_merge(
            $this->notOverrides, $this->dateOverrides, $this->nullOverrides, $this->aggregateOverrides
        );

        if (!in_array($method, $overrides)) {
            return parent::__call($method, $parameters);
        }

        list($method, $parameters) = $this->parseMethodAndParameters($method, $parameters);

        return call_user_func_array([$this, $method], $parameters);
    }

    /**
     * Get the real method name and parameters for it.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return array
     */
    protected function parseMethodAndParameters($method, array $parameters)
    {
        $paramCount = $this->getRequiredParamCount($method);

        // For date based methods we need to extract the type
        // from the called method name so it can be passed
        // to the addDateBasedWhere as first parameter.
        if (count($parameters) >= $paramCount && $this->isOverride($method, 'dateOverrides')) {
            array_unshift($parameters, substr($method, 5));

            $method = 'addDateBasedWhere';

        // For negation methods we need to extract boolean and not params
        // from the called method in order to get the real method name
        // so we will call it with parameters adjusted accordingly.
        } elseif (count($parameters) >= $paramCount && $this->isOverride($method, 'notOrNull')) {
            if (!isset($parameters[$paramCount])) {
                $parameters[$paramCount] = (strpos($method, 'or') !== false) ? 'or' : 'and';
            }

            $parameters[$paramCount+1] = (strpos($method, 'Not') !== false) ? true : false;

            $method = lcfirst(str_replace(['Not', 'or'], '', $method));

        // For aggregates we only make sure that columns are passed
        // in form of an array and add called method name as type
        // then just call the aggregate to get the real result.
        } elseif (count($parameters) >= $paramCount && $this->isOverride($method, 'aggregateOverrides')) {
            if (!is_array($parameters[0])){
                $parameters[0] = $parameters;
            }

            array_unshift($parameters, $method);

            $method = 'aggregate';
        }

        return [$method, $parameters];
    }

    /**
     * Get required parameters count for method.
     *
     * @param  string $method
     * @return int
     */
    protected function getRequiredParamCount($method)
    {
        if (in_array($method, $this->dateOverrides)) {
            return 3;
        } elseif (in_array($method, $this->notOverrides)) {
            return 2;
        }

        return 1;
    }

    /**
     * Determine whether called method should be handled by Eloquence Builder.
     *
     * @param  string  $method
     * @param  string  $group
     * @return boolean
     */
    protected function isOverride($method, $group)
    {
        return ($group == 'notOrNull')
            ? in_array($method, array_merge($this->notOverrides, $this->nullOverrides))
            : in_array($method, $this->{$group});
    }
}
