<?php

namespace Sofa\Eloquence\Contracts;

interface Metable
{
    public static function hook($method, $hook);
    public function getAttribute($key);
    public function setAttribute($key, $value);
    public function getMetaAttributes();
    public function getMetaAttributesArray();
    public function hasMeta($key);
    public function getMeta($key);
    public function setMeta($key, $value);
    public function getAllowedMeta();
}
