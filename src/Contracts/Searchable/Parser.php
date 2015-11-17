<?php

namespace Sofa\Eloquence\Contracts\Searchable;

interface Parser
{
    /**
     * Parse query string into separate words with wildcards if applicable.
     *
     * @param  string  $query
     * @param  boolean $fulltext
     * @return array
     */
    public function parseQuery($query, $fulltext = true);

    /**
     * Strip wildcard tokens from the word.
     *
     * @param  string $word
     * @return string
     */
    public function stripWildcards($word);

    /**
     * Parse searchable columns.
     *
     * @param  array|string $columns
     * @return array
     */
    public function parseWeights($columns);
}
