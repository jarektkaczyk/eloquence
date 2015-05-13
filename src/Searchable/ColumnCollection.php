<?php namespace Sofa\Eloquence\Searchable;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;

class ColumnCollection implements ArrayAccess, IteratorAggregate
{
    /** @var array */
    protected $columns = [];

    /**
     * Create new searchable columns collection.
     *
     * @param array $columns
     */
    public function __construct(array $columns = [])
    {
        foreach ($columns as $column) {
            $this->add($column);
        }
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function add(Column $column)
    {
        $this->columns[$column->getMapping()] = $column;
    }

    /**
     * Get array of qualified columns names.
     *
     * @return array
     */
    public function getQualifiedNames()
    {
        return array_map(function ($column) {
            return $column->getQualifiedName();
        }, $this->columns);
    }

    /**
     * Get array of tables names.
     *
     * @return array
     */
    public function getTables()
    {
        return array_unique(array_map(function ($column) {
            return $column->getTable();
        }, $this->columns));
    }

    /**
     * Get array of columns mappings and weights.
     *
     * @return array
     */
    public function getWeights()
    {
        $weights = [];

        foreach ($this->columns as $column) {
            $weights[$column->getMapping()] = $column->getWeight();
        }

        return $weights;
    }

    /**
     * Get array of columns mappings.
     *
     * @return array
     */
    public function getMappings()
    {
        return array_map(function ($column) {
            return $column->getMapping();
        }, $this->columns);
    }

    public function offsetExists($key)
    {
        return array_key_exists($key, $this->columns);
    }

    public function offsetGet($key)
    {
        return $this->columns[$key];
    }

    public function offsetSet($key, $column)
    {
        $this->add($column);
    }

    public function offsetUnset($key)
    {
        unset($this->columns[$key]);
    }

    /**
     * Get an iterator for the columns.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->columns);
    }
}
