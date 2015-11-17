<?php

namespace Sofa\Eloquence\Contracts;

interface Eloquence
{
    public static function getColumnsListing();
    public function hasColumn();
    public function parseMappedColumn($mapping);
    public function getSearchableColumns();
}
