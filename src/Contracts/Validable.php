<?php namespace Sofa\Eloquence\Contracts;

interface Validable
{
    public function getDirty();
    public function isValid();
    public function getValidationErrors();
    public function getInvalidAttributes();
    public function getValidator();
    public function getUpdateRules();
    public static function getCreateRules();
}
