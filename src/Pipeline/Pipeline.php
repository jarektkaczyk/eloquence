<?php namespace Sofa\Eloquence\Pipeline;

use Closure;
use Sofa\Eloquence\Contracts\Pipeline as PipelineContract;
use Sofa\Eloquence\Contracts\ArgumentBag;

class Pipeline implements PipelineContract
{
    /**
     * Actions to be called on the parcel.
     *
     * @var array
     */
    protected $pipes = [];

    /**
     * Parcel being sent through the pipeline.
     *
     * @var mixed
     */
    protected $parcel;

    /**
     * Additional parameters passed with the parcel.
     *
     * @var \Sofa\Eloquence\Contracts\ArgumentBag
     */
    protected $args;

    /**
     * Create new pipeline.
     *
     * @param array $pipes
     */
    public function __construct(array $pipes = [])
    {
        $this->pipes = $pipes;
    }

    /**
     * @inheritdoc
     */
    public function send($parcel)
    {
        $this->parcel = $parcel;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function through(array $pipes)
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function with(ArgumentBag $args)
    {
        $this->args = $args;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function to(Closure $destination)
    {
        $initialStack = $destination;

        // Actions are stacked from end to beginning, so let's reverse them.
        $pipes = array_reverse($this->pipes);

        $route = array_reduce($pipes, function ($stack, $pipe) {
            return function ($parcel, $args = null) use ($stack, $pipe) {
                return $pipe($stack, $parcel, $args);
            };
        }, $initialStack);

        return call_user_func_array($route, [$this->parcel, $this->args]);
    }
}
