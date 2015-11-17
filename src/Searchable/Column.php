<?php

namespace Sofa\Eloquence\Searchable;

use Illuminate\Database\Grammar;

class Column
{
    /** @var \Illuminate\Database\Grammar */
    protected $grammar;

    /** @var string */
    protected $table;

    /** @var string */
    protected $name;

    /** @var string */
    protected $mapping;

    /** @var integer */
    protected $weight;

    /**
     * Create new searchable column instance.
     *
     * @param string  $table
     * @param string  $name
     * @param string  $mapping
     * @param integer $weight
     */
    public function __construct(Grammar $grammar, $table, $name, $mapping, $weight = 1)
    {
        $this->grammar = $grammar;
        $this->table   = $table;
        $this->name    = $name;
        $this->mapping = $mapping;
        $this->weight  = $weight;
    }

    /**
     * Get qualified name wrapped by the grammar.
     *
     * @return string
     */
    public function getWrapped()
    {
        return $this->grammar->wrap($this->getQualifiedName());
    }

    /**
     * Get column name with table prefix.
     *
     * @return string
     */
    public function getQualifiedName()
    {
        return $this->getTable().'.'.$this->getName();
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getMapping()
    {
        return $this->mapping;
    }

    /**
     * @return integer
     */
    public function getWeight()
    {
        return $this->weight;
    }
}
