<?php namespace Sofa\Eloquence\Validable;

use Sofa\Eloquence\Contracts\Validable;

class Observer
{
    /**
     * Halt creating if model doesn't pass validation.
     *
     * @param  \Sofa\Eloquence\Contracts\Validable $model
     * @return void|false
     */
    public function creating(Validable $model)
    {
        if (!$model->isValid()) {
            return false;
        }
    }

    /**
     * Halt updating if model doesn't pass validation.
     *
     * @param  \Sofa\Eloquence\Contracts\Validable $model
     * @return void|false
     */
    public function updating(Validable $model)
    {
        // When we are trying to update this model we need to set the update rules
        // on the validator first, next we can determine if the model is valid,
        // finally we restore original rules and notify in case of failure.
        $model->getValidator()->setRules($model->getUpdateRules());

        $valid = $model->isValid();

        $model->getValidator()->setRules($model->getCreateRules());

        if (!$valid) {
            return false;
        }
    }
}
