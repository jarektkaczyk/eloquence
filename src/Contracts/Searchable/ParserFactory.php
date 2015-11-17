<?php

namespace Sofa\Eloquence\Contracts\Searchable;

use Illuminate\Database\Eloquent\Builder;

interface ParserFactory
{
    /**
     * Create new parser instance.
     *
     * @param  integer $weight
     * @param  string  $wildcard
     * @return \Sofa\Eloquence\Contracts\Searchable\Parser
     */
    public static function make($weight = 1, $wildcard = '*');
}
