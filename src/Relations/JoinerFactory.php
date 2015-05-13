<?php namespace Sofa\Eloquence\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Sofa\Eloquence\Contracts\Relations\JoinerFactory as FactoryContract;

class JoinerFactory implements FactoryContract
{
    /**
     * Create new joiner instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Sofa\Eloquence\Relations\Joiner
     */
    public function make($query, Model $model = null)
    {
        if ($query instanceof EloquentBuilder) {
            $model = $query->getModel();
            $query = $query->getQuery();
        }

        return new Joiner($query, $model);
    }
}
