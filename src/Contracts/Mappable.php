<?php namespace Sofa\Eloquence\Contracts;

interface Mappable
{
    public function getAttribute($key);
    public function setAttribute($key, $value);
    public function hasMapping($key);
    public function getMappingForAttribute($key);
    public function getMaps();
    public function setMaps(array $mappings);
}
