<?php

namespace Sofa\Eloquence\Searchable;

use Sofa\Eloquence\Contracts\Searchable\ParserFactory as FactoryContract;

class ParserFactory implements FactoryContract
{
    /**
     * Create new parser instance.
     *
     * @param  integer $weight
     * @param  string  $wildcard
     * @return \Sofa\Eloquence\Contracts\Searchable\Parser
     */
    public static function make($weight = 1, $wildcard = '*')
    {
        return new Parser($weight, $wildcard);
    }
}
