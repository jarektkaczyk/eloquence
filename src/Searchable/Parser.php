<?php

namespace Sofa\Eloquence\Searchable;

use Sofa\Eloquence\Contracts\Searchable\Parser as ParserContract;

class Parser implements ParserContract
{
    /**
     * Default search weight.
     *
     * @var integer
     */
    protected $weight;

    /**
     * Wildcard token.
     *
     * @var string
     */
    protected $wildcard;

    /**
     * Create new parser instance.
     *
     * @param integer $weight
     */
    public function __construct($weight = 1, $wildcard = '*')
    {
        $this->weight   = $weight;
        $this->wildcard = $wildcard;
    }

    /**
     * Parse searchable columns.
     *
     * @param  array|string $columns
     * @return array
     */
    public function parseWeights($columns)
    {
        if (is_string($columns)) {
            $columns = func_get_args();
        }

        return $this->addMissingWeights($columns);
    }

    /**
     * Add search weight to the columns if missing.
     *
     * @param array $columns
     */
    protected function addMissingWeights(array $columns)
    {
        $parsed = [];

        foreach ($columns as $column => $weight) {
            if (is_numeric($column)) {
                list($column, $weight) = [$weight, $this->weight];
            }

            $parsed[$column] = $weight;
        }

        return $parsed;
    }

    /**
     * Strip wildcard tokens from the word.
     *
     * @param  string $word
     * @return string
     */
    public function stripWildcards($word)
    {
        return str_replace($this->wildcard, '%', trim($word, $this->wildcard));
    }

    /**
     * Parse query string into separate words with wildcards if applicable.
     *
     * @param  string  $query
     * @param  boolean $fulltext
     * @return array
     */
    public function parseQuery($query, $fulltext = true)
    {
        $words = $this->splitString($query);

        if ($fulltext) {
            $words = $this->addWildcards($words);
        }

        return $words;
    }

    /**
     * Split query string into words/phrases to be searched.
     *
     * @param  string $query
     * @return array
     */
    protected function splitString($query)
    {
        preg_match_all('/(?<=")[\w ][^"]+(?=")|(?<=\s|^)[^\s"]+(?=\s|$)/', $query, $matches);

        return reset($matches);
    }

    /**
     * Add wildcard tokens to the words.
     *
     * @param array $words
     */
    protected function addWildcards(array $words)
    {
        $token = $this->wildcard;

        return array_map(function ($word) use ($token) {
            return preg_replace('/\*+/', '*', "{$token}{$word}{$token}");
        }, $words);
    }
}
