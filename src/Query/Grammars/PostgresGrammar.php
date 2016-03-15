<?php
namespace Sofa\Eloquence\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;


class PostgresGrammar extends BasePostgresGrammar
{
    /**
     * @var array
     */
    protected $jsonOperators = [
        '->',
        '->>',
        '#>',
        '#>>',
    ];

    /**
     * @param string $value
     *
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        // If querying hstore
        if (preg_match('/\[(.*?)\]/', $value, $match)) {
            return (string)str_replace(array('[', ']'), '', $match[1]);
        }

        // If querying json column
        foreach ($this->jsonOperators as $operator) {
            if (stripos($value, $operator)) {
                list($value, $key) = explode($operator, $value, 2);
                return parent::wrapValue($value) . $operator . $key;
            }
        }

        return parent::wrapValue($value);
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  Builder $query
     * @param  array   $where
     *
     * @return string
     */
    protected function whereNull(Builder $query, $where)
    {
        return '(' . $this->wrap($where['column']) . ') is null';
    }

    /**
     * Compile a "where not null" clause.
     *
     * @param  Builder $query
     * @param  array   $where
     *
     * @return string
     */
    protected function whereNotNull(Builder $query, $where)
    {
        return '(' . $this->wrap($where['column']) . ') is not null';
    }
}
