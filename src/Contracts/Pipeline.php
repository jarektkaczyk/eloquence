<?php

namespace Sofa\Eloquence\Contracts;

use Closure;

interface Pipeline
{
    /**
     * Specify the parcel to be passed through pipeline.
     *
     * @param  mixed $parcel
     * @return $this
     */
    public function send($parcel);

    /**
     * Specify the actions the parcel will be passed through.
     *
     * @param  array $pipes
     * @return $this
     */
    public function through(array $pipes);

    /**
     * Add the arguments to be passed along with the parcel.
     *
     * @param  \Sofa\Eloquence\Contracts\ArgumentBag $args
     * @return $this
     */
    public function with(ArgumentBag $args);

    /**
     * Dispatch the parcel and call the final callback at the end.
     *
     * @param  \Closure $destination
     * @return mixed
     */
    public function to(Closure $destination);
}
