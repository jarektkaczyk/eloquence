<?php

namespace Sofa\Eloquence\Mutator;

use ReflectionException;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Traits\Macroable;
use Sofa\Eloquence\Contracts\Mutator as MutatorContract;

class Mutator implements MutatorContract
{
    use Macroable;

    /**
     * Mutate value using provided methods.
     *
     * @param  mixed $value
     * @param  string|array $callables
     * @return mixed
     *
     * @throws \LogicException
     */
    public function mutate($value, $callables)
    {
        if (!is_array($callables)) {
            $callables = explode('|', $callables);
        }

        foreach ($callables as $callable) {
            list($callable, $args) = $this->parse(trim($callable));

            $value = call_user_func_array($callable, array_merge([$value], $args));
        }

        return $value;
    }

    /**
     * Parse provided mutator functions.
     *
     * @param  string $callable
     * @return array
     *
     * @throws \Sofa\Eloquence\Mutator\InvalidCallableException
     */
    protected function parse($callable)
    {
        list($callable, $args) = $this->parseArgs($callable);

        if ($this->isClassMethod($callable)) {
            $callable = $this->parseClassMethod($callable);
        } elseif ($this->isMutatorMethod($callable)) {
            $callable = [$this, $callable];
        } elseif (!function_exists($callable)) {
            throw new InvalidCallableException("Function [{$callable}] not found.");
        }

        return [$callable, $args];
    }

    /**
     * Determine whether callable is a class method.
     *
     * @param  string  $callable
     * @return boolean
     */
    protected function isClassMethod($callable)
    {
        return strpos($callable, '@') !== false;
    }

    /**
     * Determine whether callable is available on this instance.
     *
     * @param  string  $callable
     * @return boolean
     */
    protected function isMutatorMethod($callable)
    {
        return method_exists($this, $callable) || static::hasMacro($callable);
    }

    /**
     * Split provided string into callable and arguments.
     *
     * @param  string $callable
     * @return array
     */
    protected function parseArgs($callable)
    {
        $args = [];

        if (strpos($callable, ':') !== false) {
            list($callable, $argsString) = explode(':', $callable);

            $args = explode(',', $argsString);
        }

        return [$callable, $args];
    }

    /**
     * Extract and validate class method.
     *
     * @param  string   $userCallable
     * @return callable
     *
     * @throws \Sofa\Eloquence\Mutator\InvalidCallableException
     */
    protected function parseClassMethod($userCallable)
    {
        list($class) = explode('@', $userCallable);

        $callable = str_replace('@', '::', $userCallable);

        try {
            $method = new ReflectionMethod($callable);

            $class = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new InvalidCallableException($e->getMessage());
        }

        return ($method->isStatic()) ? $callable : $this->getInstanceMethod($class, $method);
    }

    /**
     * Get instance callable.
     *
     * @param  \ReflectionMethod  $method
     * @return callable
     *
     * @throws \Sofa\Eloquence\Mutator\InvalidCallableException
     */
    protected function getInstanceMethod(ReflectionClass $class, ReflectionMethod $method)
    {
        if (!$method->isPublic()) {
            throw new InvalidCallableException("Instance method [{$class}@{$method->getName()}] is not public.");
        }

        if (!$this->canInstantiate($class)) {
            throw new InvalidCallableException("Can't instantiate class [{$class->getName()}].");
        }

        return [$class->newInstance(), $method->getName()];
    }

    /**
     * Determine whether instance can be instantiated.
     *
     * @param  \ReflectionClass  $class
     * @return boolean
     */
    protected function canInstantiate(ReflectionClass $class)
    {
        if (!$class->isInstantiable()) {
            return false;
        }

        $constructor = $class->getConstructor();

        return is_null($constructor) || 0 === $constructor->getNumberOfRequiredParameters();
    }
}
