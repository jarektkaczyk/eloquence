<?php

namespace Sofa\Eloquence\Contracts;

interface CleansAttributes
{
    public static function getColumnListing();
    public function getDirty();
}
