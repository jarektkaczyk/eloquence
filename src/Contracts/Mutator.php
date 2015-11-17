<?php

namespace Sofa\Eloquence\Contracts;

interface Mutator
{
    /**
     * Mutate value using provided methods.
     *
     * @param  mixed $value
     * @param  string|array $mutators
     * @return mixed
     *
     * @throws \LogicException
     */
    public function mutate($value, $mutators);
}
