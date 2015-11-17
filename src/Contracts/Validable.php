<?php

namespace Sofa\Eloquence\Contracts;

interface Validable
{
    public function getDirty();
    public function isValid();
    public function enableValidation();
    public function disableValidation($once = false);
    public function validationEnabled();
    public function skipsValidation();
    public function getValidationErrors();
    public function getInvalidAttributes();
    public function getValidator();
    public function getUpdateRules();
    public static function getCreateRules();
}
