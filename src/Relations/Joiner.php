<?php

namespace Sofa\Eloquence\Relations;

use LogicException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause as Join;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Sofa\Eloquence\Contracts\Relations\Joiner as JoinerContract;

class Joiner implements JoinerContract
{
    /**
     * Processed query instance.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    protected $query;

    /**
     * Parent model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Create new joiner instance.
     *
     * @param \Illuminate\Database\Query\Builder
     * @param \Illuminate\Database\Eloquent\Model
     */
    public function __construct(Builder $query, Model $model)
    {
        $this->query = $query;
        $this->model = $model;
    }

    /**
     * Join related tables.
     *
     * @param  string $target
     * @param  string $type
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function join($target, $type = 'inner')
    {
        $related = $this->model;

        foreach (explode('.', $target) as $segment) {
            $related = $this->joinSegment($related, $segment, $type);
        }

        return $related;
    }

    /**
     * Left join related tables.
     *
     * @param  string $target
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function leftJoin($target)
    {
        return $this->join($target, 'left');
    }

    /**
     * Right join related tables.
     *
     * @param  string $target
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function rightJoin($target)
    {
        return $this->join($target, 'right');
    }

    /**
     * Join relation's table accordingly.
     *
     * @param  \Illuminate\Database\Eloquent\Model $parent
     * @param  string $segment
     * @param  string $type
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function joinSegment(Model $parent, $segment, $type)
    {
        $relation = $parent->{$segment}();
        $related  = $relation->getRelated();
        $table    = $related->getTable();

        if ($relation instanceof BelongsToMany || $relation instanceof HasManyThrough) {
            $this->joinIntermediate($parent, $relation, $type);
        }

        if (!$this->alreadyJoined($join = $this->getJoinClause($parent, $relation, $table, $type))) {
            $this->query->joins[] = $join;
        }

        return $related;
    }

    /**
     * Determine whether the related table has been already joined.
     *
     * @param  \Illuminate\Database\Query\JoinClause $join
     * @return boolean
     */
    protected function alreadyJoined(Join $join)
    {
        return in_array($join, (array) $this->query->joins);
    }

    /**
     * Get the join clause for related table.
     *
     * @param  \Illuminate\Database\Eloquent\Model $parent
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param  string $type
     * @param  string $table
     * @return \Illuminate\Database\Query\JoinClause
     */
    protected function getJoinClause(Model $parent, Relation $relation, $table, $type)
    {
        list($fk, $pk) = $this->getJoinKeys($relation);

        $join = (new Join($this->query, $type, $table))->on($fk, '=', $pk);

        if ($relation instanceof MorphOneOrMany) {
            $join->where($relation->getQualifiedMorphType(), '=', $parent->getMorphClass());
        }

        return $join;
    }

    /**
     * Join pivot or 'through' table.
     *
     * @param  \Illuminate\Database\Eloquent\Model $parent
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param  string $type
     * @return void
     */
    protected function joinIntermediate(Model $parent, Relation $relation, $type)
    {
        if ($relation instanceof BelongsToMany) {
            $table = $relation->getTable();
            $fk = $relation->getQualifiedForeignKeyName();
        } else {
            $table = $relation->getParent()->getTable();
            $fk = $relation->getQualifiedFirstKeyName();
        }

        $pk = $parent->getQualifiedKeyName();

        if (!$this->alreadyJoined($join = (new Join($this->query, $type, $table))->on($fk, '=', $pk))) {
            $this->query->joins[] = $join;
        }
    }

    /**
     * Get pair of the keys from relation in order to join the table.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @return array
     *
     * @throws \LogicException
     */
    protected function getJoinKeys(Relation $relation)
    {
        if ($relation instanceof MorphTo) {
            throw new LogicException("MorphTo relation cannot be joined.");
        }

        if ($relation instanceof HasOneOrMany) {
            return [$relation->getQualifiedForeignKeyName(), $relation->getQualifiedParentKeyName()];
        }

        if ($relation instanceof BelongsTo) {
            return [$relation->getQualifiedForeignKey(), $relation->getQualifiedOwnerKeyName()];
        }

        if ($relation instanceof BelongsToMany) {
            return [$relation->getQualifiedRelatedKeyName(), $relation->getRelated()->getQualifiedKeyName()];
        }

        if ($relation instanceof HasManyThrough) {
            $fk = $relation->getQualifiedFarKeyName();

            return [$fk, $relation->getParent()->getQualifiedKeyName()];
        }
    }
}
