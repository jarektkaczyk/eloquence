<?php namespace Sofa\Eloquence\Contracts;

interface CleansAttributes
{
    public function getColumnListing();
    public function getDirty();
}
