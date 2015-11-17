<?php

namespace Sofa\Eloquence\Contracts\Relations;

interface Joiner
{
    /**
     * Join tables of the provided relations and return related model.
     *
     * @param  string $relations
     * @param  string $type
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function join($relations, $type = 'inner');
}
