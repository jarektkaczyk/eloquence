<?php

namespace Sofa\Eloquence\Contracts;

use Closure;

interface Mappable
{
    public static function hook($method, Closure $hook);
    public function getAttribute($key);
    public function setAttribute($key, $value);
    public function hasMapping($key);
    public function getMappingForAttribute($key);
    public function getMaps();
}
