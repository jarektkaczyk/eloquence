<?php namespace Sofa\Eloquence;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Sofa\Eloquence\Contracts\Mappable as MappableContract;

class Builder extends EloquentBuilder
{

    /**
     * Add where constraint to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If developer provided column prefixed with table name we will
        // not even try to map the column, since obviously the value
        // refers to the actual column name on the queried table
        if ($this->notPrefixed($column)) {
            $column = $this->getColumnMapping($column);

            if ($this->nestedMapping($column)) {
                return $this->mappedWhere($column, $operator, $value, $boolean);
            }
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Determine whether the column was not passed with table prefix.
     *
     * @param  string $column
     * @return boolean
     */
    protected function notPrefixed($column)
    {
        return strpos($column, '.') === false;
    }

    /**
     * Get the mapping for a column if exists or simply return the column.
     *
     * @param  string $column
     * @return string
     */
    protected function getColumnMapping($column)
    {
        $model = $this->getModel();

        if (is_string($column) && $model instanceof MappableContract && $model->hasMapping($column)) {
            $column = $model->getMappingForAttribute($column);
        }

        return $column;
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
     * Add a relationship count condition to the query with where clauses.
     *
     * @param  string  $mapping
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     */
    protected function mappedWhere($mapping, $operator, $value, $boolean)
    {
        list($target, $column) = $this->parseMapping($mapping);

        return $this->has($target, '>=', 1, $boolean, function ($q) use ($column, $operator, $value) {
            $q->where($column, $operator, $value);
        });
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
}
