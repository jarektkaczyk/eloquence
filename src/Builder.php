<?php namespace Sofa\Eloquence;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    /**
     * Add where constraint to the query.
     *
     * @param  string  $key
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($key, $operator = null, $value = null, $boolean = 'and')
    {
        if ($this->isCustomWhere($key)) {
            $args = compact('key', 'operator', 'value', 'boolean');

            return $this->getModel()->customWhere($this, $args);
        }

        return call_user_func_array('parent::where', func_get_args());
    }

    /**
     * Call base eloquent where.
     *
     * @return $this
     */
    public function baseWhere()
    {
        return call_user_func_array('parent::where', func_get_args());
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
}
