<?php namespace Sofa\Eloquence;

use Closure;
use InvalidArgumentException;
use Sofa\Eloquence\Contracts\Relations\JoinerFactory;
use Sofa\Eloquence\Contracts\Searchable\ParserFactory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Sofa\Eloquence\Searchable\Subquery as SearchableSubquery;
use Sofa\Eloquence\Searchable\Column;
use Sofa\Eloquence\Searchable\ColumnCollection;

/**
 * @method $this leftJoin($table, $one, $operator, $two)
 */
class Builder extends EloquentBuilder
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
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = array(
        'toSql', 'lists', 'insert', 'insertGetId', 'pluck', 'value', 'count', 'raw',
        'min', 'max', 'avg', 'sum', 'exists', 'getBindings', 'aggregate',
    );

    /*
    |--------------------------------------------------------------------------
    | Additional features
    |--------------------------------------------------------------------------
    */

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
                        : (float) $threshold ;

        $subquery->select($this->model->getTable().'.*')
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
        $joinBindings = array_flatten(array_pluck((array)$subquery->getQuery()->joins, 'bindings'));

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

                $where['column'] = $this->model->getTable().'.'.$where['column'];

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
            return substr_count($where['column'].$where['value'], '?');

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
                    $bindings['select'][] = $bindings['where'][$key] = $this->caseBinding($word).'%';
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
                    $bindings['select'][] = $bindings['where'][$key] = '%'.$this->caseBinding($word).'%';
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
     * @return void
     */
    public function prefixColumnsForJoin()
    {
        if (!$columns = $this->query->columns) {
            return $this->select($this->model->getTable().'.*');
        }

        foreach ($columns as $key => $column) {
            if ($this->model->hasColumn($column)) {
                $columns[$key] = $this->model->getTable().'.'.$column;
            }
        }

        $this->query->columns = $columns;
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

    /*
    |--------------------------------------------------------------------------
    | Hooks handling
    |--------------------------------------------------------------------------
    */

    /**
     * Call base Eloquent method.
     *
     * @param  string $method
     * @param  array  $args
     * @return mixed
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
     * @return mixed
     */
    protected function callHook($method, ArgumentBag $args)
    {
        if ($this->hasHook($args->get('column')) || in_array($method, ['select', 'addSelect'])) {
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
     * Set the columns to be selected.
     *
     * @param  array  $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->callHook(__FUNCTION__, $this->packArgs(compact('columns')));
    }

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
     * Add an "or where" clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  boolean $not
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        if (($count = count($values)) != 2) {
            throw new InvalidArgumentException(
                "Between clause requires exactly 2 values, {$count} given."
            );
        }

        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column', 'values', 'boolean', 'not')));
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
        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column', 'values', 'boolean', 'not')));
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
        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column', 'boolean', 'not')));
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
     * Add a "where date" statement to the query.
     *
     * @param  string  $column
     * @param  string   $operator
     * @param  int   $value
     * @param  string   $boolean
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
     * @param  string   $operator
     * @param  int   $value
     * @param  string   $boolean
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
     * @param  string   $operator
     * @param  int   $value
     * @param  string   $boolean
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
     * @param  string   $operator
     * @param  int   $value
     * @param  string   $boolean
     * @return $this
     */
    public function whereYear($column, $operator, $value, $boolean = 'and')
    {
        return $this->addDateBasedWhere('Year', $column, $operator, $value, $boolean);
    }

    /**
     * Add an exists clause to the query.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @param  bool     $not
     * @return $this
     */
    public function whereExists(Closure $callback, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotExists' : 'Exists';

        $builder = $this->newQuery();

        call_user_func($callback, $builder);

        $query = $builder->getQuery();

        $this->query->wheres[] = compact('type', 'query', 'boolean');

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
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        return $this->value($column);
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function value($column)
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
    public function aggregate($function, array $columns = ['*'])
    {
        $column = (reset($columns) !== '*') ? reset($columns) : null;

        return $this->callHook(__FUNCTION__, $this->packArgs(compact('function', 'columns', 'column')));
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, (array) $column);
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, (array) $column);
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, (array) $column);
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        return $this->aggregate(__FUNCTION__, (array) $column);
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function count($columns = '*')
    {
        return $this->aggregate(__FUNCTION__, (array) $columns);
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string  $key
     * @return array
     */
    public function lists($column, $key = null)
    {
        return $this->callHook(__FUNCTION__, $this->packArgs(compact('column', 'key')));
    }

    /**
     * Get a new instance of the Eloquence query builder.
     *
     * @return \Sofa\Eloquence\Builder
     */
    public function newQuery()
    {
        return $this->model->newQueryWithoutScopes();
    }
}
