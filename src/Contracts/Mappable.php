<?php namespace Sofa\Eloquence\Contracts;

interface Mappable
{
    public function hasMapping($key);
    public function mapAttribute($key);
    public function getMappingForAttribute($key);
    public function getMaps();
    public function setMaps(array $mappings);
}
