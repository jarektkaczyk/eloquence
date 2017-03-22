<?php

namespace Sofa\Eloquence;

use Sofa\Eloquence\Searchable\Column;
use Illuminate\Database\Query\Expression;
use Sofa\Hookable\Builder as HookableBuilder;
use Sofa\Eloquence\Searchable\ColumnCollection;
use Sofa\Eloquence\Contracts\Relations\JoinerFactory;
use Sofa\Eloquence\Contracts\Searchable\ParserFactory;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Sofa\Eloquence\Searchable\Subquery as SearchableSubquery;

/**
 * @method $this leftJoin($table, $one, $operator, $two)
 */
class Builder extends HookableBuilder
{
    /**
     * Parser factory instance.
     *
     * @var \Sofa\Eloquence\Contracts\Searchable\ParserFactory
     */
    protected static $parser;

    /**
     * Joiner factory instance.
     *
     * @var \Sofa\Eloquence\Contracts\Relations\JoinerFactory
     */
    protected static $joinerFactory;

    /**
     * Relations joiner instance.
     *
     * @var \Sofa\Eloquence\Contracts\Relations\Joiner
     */
    protected $joiner;

    /*
    |--------------------------------------------------------------------------
    | Additional features
    |--------------------------------------------------------------------------
    */

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        if ($this->query->from instanceof Subquery) {
            $this->wheresToSubquery($this->query->from);
        }

        return parent::get($columns);
    }

    /**
     * Search through any columns on current table or any defined relations
     * and return results ordered by search relevance.
     *
     * @param  array|string $query
     * @param  array $columns
     * @param  boolean $fulltext
     * @param  float $threshold
     * @return $this
     */
    public function search($query, $columns = null, $fulltext = true, $threshold = null)
    {
        if (is_bool($columns)) {
            list($fulltext, $columns) = [$columns, []];
        }

        $parser = static::$parser->make();

        $words = is_array($query) ? $query : $parser->parseQuery($query, $fulltext);

        $columns = $parser->parseWeights($columns ?: $this->model->getSearchableColumns());

        if (count($words) && count($columns)) {
            $this->query->from($this->buildSubquery($words, $columns, $threshold));
        }

        return $this;
    }

    /**
     * Build the search subquery.
     *
     * @param  array $words
     * @param  array $mappings
     * @param  float $threshold
     * @return \Sofa\Eloquence\Searchable\Subquery
     */
    protected function buildSubquery(array $words, array $mappings, $threshold)
    {
        $subquery = new SearchableSubquery($this->query->newQuery(), $this->model->getTable());

        $columns = $this->joinForSearch($mappings, $subquery);

        $threshold = (is_null($threshold))
                        ? array_sum($columns->getWeights()) / 4
                        : (float) $threshold;

        $subquery->select($this->model->getTable() . '.*')
                 ->from($this->model->getTable())
                 ->groupBy($this->model->getQualifiedKeyName());

        $this->addSearchClauses($subquery, $columns, $words, $threshold);

        return $subquery;
    }

    /**
     * Add select and where clauses on the subquery.
     *
     * @param  \Sofa\Eloquence\Searchable\Subquery $subquery
     * @param  \Sofa\Eloquence\Searchable\ColumnCollection $columns
     * @param  array $words
     * @param  float $threshold
     * @return void
     */
    protected function addSearchClauses(
        SearchableSubquery $subquery,
        ColumnCollection $columns,
        array $words,
        $threshold
    ) {
        $whereBindings = $this->searchSelect($subquery, $columns, $words, $threshold);

        // For morphOne/morphMany support we need to port the bindings from JoinClauses.
        $joinBindings = collect($subquery->getQuery()->joins)->flatMap(function ($join) {
            return $join->getBindings();
        })->all();

        $this->addBinding($joinBindings, 'select');

        // Developer may want to skip the score threshold filtering by passing zero
        // value as threshold in order to simply order full result by relevance.
        // Otherwise we are going to add where clauses for speed improvement.
        if ($threshold > 0) {
            $this->searchWhere($subquery, $columns, $words, $whereBindings);
        }

        $this->query->where('relevance', '>=', new Expression($threshold));

        $this->query->orders = array_merge(
            [['column' => 'relevance', 'direction' => 'desc']],
            (array) $this->query->orders
        );
    }

    /**
     * Apply relevance select on the subquery.
     *
     * @param  \Sofa\Eloquence\Searchable\Subquery $subquery
     * @param  \Sofa\Eloquence\Searchable\ColumnCollection $columns
     * @param  array $words
     * @return array
     */
    protected function searchSelect(SearchableSubquery $subquery, ColumnCollection $columns, array $words)
    {
        $cases = $bindings = [];

        foreach ($columns as $column) {
            list($cases[], $binding) = $this->buildCase($column, $words);

            $bindings = array_merge_recursive($bindings, $binding);
        }

        $select = implode(' + ', $cases);

        $subquery->selectRaw("max({$select}) as relevance");

        $this->addBinding($bindings['select'], 'select');

        return $bindings['where'];
    }

    /**
     * Apply where clauses on the subquery.
     *
     * @param  \Sofa\Eloquence\Searchable\Subquery $subquery
     * @param  \Sofa\Eloquence\Searchable\ColumnCollection $columns
     * @param  array $words
     * @return void
     */
    protected function searchWhere(
        SearchableSubquery $subquery,
        ColumnCollection $columns,
        array $words,
        array $bindings
    ) {
        $operator = $this->getLikeOperator();

        $wheres = [];

        foreach ($columns as $column) {
            $wheres[] = implode(
                ' or ',
                array_fill(0, count($words), sprintf('%s %s ?', $column->getWrapped(), $operator))
            );
        }

        $where = implode(' or ', $wheres);

        $subquery->whereRaw("({$where})");

        $this->addBinding($bindings, 'select');
    }

    /**
     * Move where clauses to subquery to improve performance.
     *
     * @param  \Sofa\Eloquence\Searchable\Subquery $subquery
     * @return void
     */
    protected function wheresToSubquery(SearchableSubquery $subquery)
    {
        $bindingKey = 0;

        $typesToMove = [
            'basic', 'in', 'notin', 'between', 'null',
            'notnull', 'date', 'day', 'month', 'year',
        ];

        // Here we are going to move all the where clauses that we might apply
        // on the subquery in order to improve performance, since this way
        // we can drastically reduce number of joined rows on subquery.
        foreach ((array) $this->query->wheres as $key => $where) {
            $type = strtolower($where['type']);

            $bindingsCount = $this->countBindings($where, $type);

            if (in_array($type, $typesToMove) && $this->model->hasColumn($where['column'])) {
                unset($this->query->wheres[$key]);

                $where['column'] = $this->model->getTable() . '.' . $where['column'];

                $subquery->getQuery()->wheres[] = $where;

                $whereBindings = $this->query->getRawBindings()['where'];

                $bindings = array_splice($whereBindings, $bindingKey, $bindingsCount);

                $this->query->setBindings($whereBindings, 'where');

                $this->query->addBinding($bindings, 'select');

            // if where is not to be moved onto the subquery, let's increment
            // binding key appropriately, so we can reliably move binding
            // for the next where clauses in the loop that is running.
            } else {
                $bindingKey += $bindingsCount;
            }
        }
    }

    /**
     * Get number of bindings provided for a where clause.
     *
     * @param  array   $where
     * @param  string  $type
     * @return integer
     */
    protected function countBindings(array $where, $type)
    {
        if ($this->isHasWhere($where, $type)) {
            return substr_count($where['column'] . $where['value'], '?');
        } elseif ($type === 'basic') {
            return (int) !$where['value'] instanceof Expression;
        } elseif (in_array($type, ['basic', 'date', 'year', 'month', 'day'])) {
            return (int) !$where['value'] instanceof Expression;
        } elseif (in_array($type, ['null', 'notnull'])) {
            return 0;
        } elseif ($type === 'between') {
            return 2;
        } elseif (in_array($type, ['in', 'notin'])) {
            return count($where['values']);
        } elseif ($type === 'raw') {
            return substr_count($where['sql'], '?');
        } elseif (in_array($type, ['nested', 'sub', 'exists', 'notexists', 'insub', 'notinsub'])) {
            return count($where['query']->getBindings());
        }
    }

    /**
     * Determine whether where clause is eloquent has subquery.
     *
     * @param  array  $where
     * @param  string $type
     * @return boolean
     */
    protected function isHasWhere($where, $type)
    {
        return $type === 'basic'
                && $where['column'] instanceof Expression
                && $where['value'] instanceof Expression;
    }

    /**
     * Build case clause from all words for a single column.
     *
     * @param  \Sofa\Eloquence\Searchable\Column $column
     * @param  array  $words
     * @return array
     */
    protected function buildCase(Column $column, array $words)
    {
        // THIS IS BAD
        // @todo refactor

        $operator = $this->getLikeOperator();

        $bindings['select'] = $bindings['where'] = array_map(function ($word) {
            return $this->caseBinding($word);
        }, $words);

        $case = $this->buildEqualsCase($column, $words);

        if (strpos(implode('', $words), '*') !== false) {
            $leftMatching = [];

            foreach ($words as $key => $word) {
                if ($this->isLeftMatching($word)) {
                    $leftMatching[] = sprintf('%s %s ?', $column->getWrapped(), $operator);
                    $bindings['select'][] = $bindings['where'][$key] = $this->caseBinding($word) . '%';
                }
            }

            if (count($leftMatching)) {
                $leftMatching = implode(' or ', $leftMatching);
                $score = 5 * $column->getWeight();
                $case .= " + case when {$leftMatching} then {$score} else 0 end";
            }

            $wildcards = [];

            foreach ($words as $key => $word) {
                if ($this->isWildcard($word)) {
                    $wildcards[] = sprintf('%s %s ?', $column->getWrapped(), $operator);
                    $bindings['select'][] = $bindings['where'][$key] = '%'.$this->caseBinding($word) . '%';
                }
            }

            if (count($wildcards)) {
                $wildcards = implode(' or ', $wildcards);
                $score = 1 * $column->getWeight();
                $case .= " + case when {$wildcards} then {$score} else 0 end";
            }
        }

        return [$case, $bindings];
    }

    /**
     * Replace '?' with single character SQL wildcards.
     *
     * @param  string $word
     * @return string
     */
    protected function caseBinding($word)
    {
        $parser = static::$parser->make();

        return str_replace('?', '_', $parser->stripWildcards($word));
    }

    /**
     * Build basic search case for 'equals' comparison.
     *
     * @param  \Sofa\Eloquence\Searchable\Column $column
     * @param  array  $words
     * @return string
     */
    protected function buildEqualsCase(Column $column, array $words)
    {
        $equals = implode(' or ', array_fill(0, count($words), sprintf('%s = ?', $column->getWrapped())));

        $score = 15 * $column->getWeight();

        return "case when {$equals} then {$score} else 0 end";
    }

    /**
     * Determine whether word ends with wildcard.
     *
     * @param  string  $word
     * @return boolean
     */
    protected function isLeftMatching($word)
    {
        return ends_with($word, '*');
    }

    /**
     * Determine whether word starts and ends with wildcards.
     *
     * @param  string  $word
     * @return boolean
     */
    protected function isWildcard($word)
    {
        return ends_with($word, '*') && starts_with($word, '*');
    }

    /**
     * Get driver-specific case insensitive like operator.
     *
     * @return string
     */
    public function getLikeOperator()
    {
        $grammar = $this->query->getGrammar();

        if ($grammar instanceof PostgresGrammar) {
            return 'ilike';
        }

        return 'like';
    }

    /**
     * Join related tables on the search subquery.
     *
     * @param  array $mappings
     * @param  \Sofa\Eloquence\Searchable\Subquery $subquery
     * @return \Sofa\Eloquence\Searchable\ColumnCollection
     */
    protected function joinForSearch($mappings, $subquery)
    {
        $mappings = is_array($mappings) ? $mappings : (array) $mappings;

        $columns = new ColumnCollection;

        $grammar = $this->query->getGrammar();

        $joiner = static::$joinerFactory->make($subquery->getQuery(), $this->model);

        // Here we loop through the search mappings in order to join related tables
        // appropriately and build a searchable column collection, which we will
        // use to build select and where clauses with correct table prefixes.
        foreach ($mappings as $mapping => $weight) {
            if (strpos($mapping, '.') !== false) {
                list($relation, $column) = $this->model->parseMappedColumn($mapping);

                $related = $joiner->leftJoin($relation);

                $columns->add(
                    new Column($grammar, $related->getTable(), $column, $mapping, $weight)
                );
            } else {
                $columns->add(
                    new Column($grammar, $this->model->getTable(), $mapping, $mapping, $weight)
                );
            }
        }

        return $columns;
    }

    /**
     * Prefix selected columns with table name in order to avoid collisions.
     *
     * @return $this
     */
    public function prefixColumnsForJoin()
    {
        if (!$columns = $this->query->columns) {
            return $this->select($this->model->getTable() . '.*');
        }

        foreach ($columns as $key => $column) {
            if ($this->model->hasColumn($column)) {
                $columns[$key] = $this->model->getTable() . '.' . $column;
            }
        }

        $this->query->columns = $columns;

        return $this;
    }

    /**
     * Join related tables.
     *
     * @param  array|string $relations
     * @param  string $type
     * @return $this
     */
    public function joinRelations($relations, $type = 'inner')
    {
        if (is_null($this->joiner)) {
            $this->joiner = static::$joinerFactory->make($this);
        }

        if (!is_array($relations)) {
            list($relations, $type) = [func_get_args(), 'inner'];
        }

        foreach ($relations as $relation) {
            $this->joiner->join($relation, $type);
        }

        return $this;
    }

    /**
     * Left join related tables.
     *
     * @param  array|string $relations
     * @return $this
     */
    public function leftJoinRelations($relations)
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        return $this->joinRelations($relations, 'left');
    }

    /**
     * Right join related tables.
     *
     * @param  array|string $relations
     * @return $this
     */
    public function rightJoinRelations($relations)
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        return $this->joinRelations($relations, 'right');
    }

    /**
     * Set search query parser factory instance.
     *
     * @param \Sofa\Eloquence\Contracts\Searchable\ParserFactory $factory
     */
    public static function setParserFactory(ParserFactory $factory)
    {
        static::$parser = $factory;
    }

    /**
     * Set the relations joiner factory instance.
     *
     * @param \Sofa\Eloquence\Contracts\Relations\JoinerFactory $factory
     */
    public static function setJoinerFactory(JoinerFactory $factory)
    {
        static::$joinerFactory = $factory;
    }
}
