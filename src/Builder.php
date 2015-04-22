<?php namespace Sofa\Eloquence;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    // orderBy

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'like binary', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
                'not similar to',
    );

    /**
     * Call base handler for where call.
     *
     * @param  string $method
     * @param  array  $args
     * @return $this
     */
    public function parentWhere($method, array $args)
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
    protected function customWhere($method, ArgumentBag $args)
    {
        return $this->getModel()->customWhere($this, $method, $args);
    }

    /**
     * Determine whether where call might have custom handler.
     *
     * @param  string  $column
     * @return boolean
     */
    protected function isCustomWhere($column)
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
        if ($this->isCustomWhere($column)) {
            if (!in_array(strtolower($operator), $this->operators, true)) {
                list($value, $operator) = [$operator, '='];
            }

            $bag = $this->packArgs(compact('column', 'operator', 'value', 'boolean'));

            return $this->customWhere(__FUNCTION__, $bag);
        }

        return $this->parentWhere(__FUNCTION__, func_get_args());
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
        if ($this->isCustomWhere($column)) {
            $bag = $this->packArgs(compact('column', 'values', 'boolean', 'not'));

            return $this->customWhere(__FUNCTION__, $bag);
        }

        return $this->parentWhere(__FUNCTION__, func_get_args());
    }

    /**
     * Add an or where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @return $this
     */
    public function orWhereBetween($column, array $values)
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Add a where not between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotBetween($column, array $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add an or where not between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @return $this
     */
    public function orWhereNotBetween($column, array $values)
    {
        return $this->whereNotBetween($column, $values, 'or');
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
        if ($this->isCustomWhere($column)) {
            $bag = $this->packArgs(compact('column', 'values', 'boolean', 'not'));

            return $this->customWhere(__FUNCTION__, $bag);
        }

        return $this->parentWhere(__FUNCTION__, func_get_args());
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return $this
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return $this
     */
    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'or');
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
        if ($this->isCustomWhere($column)) {
            $bag = $this->packArgs(compact('column', 'boolean', 'not'));

            return $this->customWhere(__FUNCTION__, $bag);
        }

        return $this->parentWhere(__FUNCTION__, func_get_args());
    }

    /**
     * Add an "or where null" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotNull($column, $boolean = 'and')
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add an "or where not null" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add a "where date" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  int     $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereDate($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Date', $column, $operator, $value, $boolean);
    }

    /**
     * Add a "where day" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  int     $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereDay($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Day', $column, $operator, $value, $boolean);
    }

    /**
     * Add a "where month" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  int     $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereMonth($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Month', $column, $operator, $value, $boolean);
    }

    /**
     * Add a "where year" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  int     $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereYear($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Year', $column, $operator, $value, $boolean);
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
        $bag = $this->packArgs(compact('column', 'operator', 'value', 'boolean'));

        if ($this->isCustomWhere($column)) {

            return $this->customWhere("where{$type}", $bag);
        }

        return $this->parentWhere("where{$type}", $args->all());
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
        if ($this->isCustomWhere($column)) {
            $bag = $this->packArgs(compact('column', 'direction'));

            return $this->customWhere(__FUNCTION__, $bag);
        }

        return $this->parentWhere(__FUNCTION__, func_get_args());
    }

    /**
     * Pluck a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        if ($this->isCustomWhere($column)) {
            $bag = $this->packArgs(compact('column'));

            return $this->customWhere(__FUNCTION__, $bag);
        }

        return $this->parentWhere(__FUNCTION__, func_get_args());
    }

    public function newQuery()
    {
        return new static($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function count($columns = '*')
    {
        if ( ! is_array($columns))
        {
            $columns = array($columns);
        }

        return (int) $this->aggregate(__FUNCTION__, $columns);
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        $result = $this->aggregate(__FUNCTION__, array($column));

        return $result ?: 0;
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
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
        if (count($columns) && ($column = reset($columns)) != '*' && $this->isCustomWhere($column)) {
            $bag = $this->packArgs(compact('function', 'columns', 'column'));

            return $this->customWhere(__FUNCTION__, $bag);
        }

        return $this->parentWhere(__FUNCTION__, func_get_args());
    }
}
