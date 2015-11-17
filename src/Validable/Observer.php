<?php

namespace Sofa\Eloquence\Validable;

use Sofa\Eloquence\Contracts\Validable;

class Observer
{
    /**
     * Validation skipping flag.
     *
     * @var integer
     */
    const SKIP_ONCE = 1;

    /**
     * Validation skipping flag.
     *
     * @var integer
     */
    const SKIP_ALWAYS = 2;

    /**
     * Halt creating if model doesn't pass validation.
     *
     * @param  \Sofa\Eloquence\Contracts\Validable $model
     * @return void|false
     */
    public function creating(Validable $model)
    {
        if ($model->validationEnabled() && !$model->isValid()) {
            return false;
        }
    }

    /**
     * Updating event handler.
     *
     * @param  \Sofa\Eloquence\Contracts\Validable $model
     * @return void|false
     */
    public function updating(Validable $model)
    {
        if ($model->validationEnabled()) {
            return $this->validateUpdate($model);
        }
    }

    /**
     * Halt updating if model doesn't pass validation.
     *
     * @param  \Sofa\Eloquence\Contracts\Validable $model
     * @return void|false
     */
    protected function validateUpdate(Validable $model)
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

    /**
     * Enable validation for the model if skipped only once.
     *
     * @param  \Sofa\Eloquence\Contracts\Validable $model
     * @return void
     */
    public function saved(Validable $model)
    {
        if ($model->skipsValidation() === static::SKIP_ONCE) {
            $model->enableValidation();
        }
    }
}
