<?php

namespace Sofa\Eloquence\AttributeCleaner;

use Sofa\Eloquence\Contracts\CleansAttributes;

class Observer
{
    /**
     * Saving event handler.
     *
     * @param  mixed $model
     * @return void
     */
    public function saving($model)
    {
        if ($model instanceof CleansAttributes) {
            $this->cleanAttributes($model);
        }
    }

    /**
     * Get rid of attributes that are not correct columns on this model's table.
     *
     * @param  \Sofa\Eloquence\Contracts\CleansAttributes $model
     * @return void
     */
    protected function cleanAttributes(CleansAttributes $model)
    {
        $dirty = array_keys($model->getDirty());

        $invalidColumns = array_diff($dirty, $model->getColumnListing());

        foreach ($invalidColumns as $column) {
            unset($model->{$column});
        }
    }
}
