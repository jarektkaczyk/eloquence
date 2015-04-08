<?php namespace Sofa\Eloquence;

use InvalidArgumentException;
use ReflectionMethod;
use ReflectionException;

/**
 * @property array $formats
 */
trait Formattable
{
    /**
     * Register hooks for the trait.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public static function bootFormattable()
    {
        foreach (['setAttribute'] as $method) {
            static::hook($method, "{$method}Formattable");
        }
    }

    /**
     * Register hook on setAttribute method.
     *
     * @codeCoverageIgnore
     *
     * @return \Closure
     */
    public function setAttributeFormattable()
    {
        return function ($next, $value, $args) {
            $key = $args->get('key');

            if ($this->hasFormatting($key)) {
                $value = $this->formatAttribute($key, $value);
            }

            return $next($value, $args);
        };
    }

    /**
     * Determine whether a formating exists for an attribute.
     *
     * @param  string  $key
     * @return boolean
     */
    public function hasFormatting($key)
    {
        $formats = $this->getFormats();

        return array_key_exists($key, $formats);
    }

    /**
     * Format the attribute.
     *
     * @param  string $key
     * @param  string $value
     * @return string
     */
    protected function formatAttribute($key, $value)
    {
        $format = $this->getFormatForAttribute($key);

        if ($this->hasMultipleMethods($format)) {
            return $this->callMultipleMethods($value, $format);
        }

        return $this->callSingleMethod($value, $format);
    }

    /**
     * Determine if the format has multiple methods to call.
     *
     * @param  string $format
     * @return boolean
     */
    protected function hasMultipleMethods($format)
    {
        return is_array($format) || strpos($format, '|') !== false;
    }

    /**
     * Call all methods specified.
     *
     * @param  string $value
     * @param  array|string $methods
     * @return string
     */
    protected function callMultipleMethods($value, $methods)
    {
        $methods = $this->transformFormatToArray($methods);

        foreach ($methods as $method) {
            $value = $this->callSingleMethod($value, $method);
        }

        return $value;
    }

    /**
     * Apply the formatting method.
     *
     * @param  string $value
     * @param  string $method
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function callSingleMethod($value, $method)
    {
        list($method, $args) = $this->parseMethodArguments($method);

        array_unshift($args, $value);

        if ($this->isGlobalFunction($method)) {
            return $this->callGlobalFunction($method, $args);
        }

        if ($this->hasClassMethod($method)) {
            return $this->callClassMethod($method, $args);
        }

        if ($this->isStaticClassCall($method) && $this->isValidStaticMethod($method)) {
            return $this->callStaticMethod($method, $args);
        }

        throw new InvalidArgumentException("Formatting method [{$method}] not found.");
    }

    /**
     * Parse additional formatting arguments.
     *
     * @param  string $method
     * @return array
     */
    protected function parseMethodArguments($method)
    {
        $args = [];

        if (strpos($method, ':') !== false) {
            list($method, $argsString) = explode(':', $method, 2);

            $args = explode(',', $argsString);
        }

        return [$method, $args];
    }

    /**
     * Transform a string with a pipe to an array.
     *
     * @param  string $format
     * @return array
     */
    protected function transformFormatToArray($format)
    {
        return (is_array($format)) ? $format : explode('|', $format);
    }

    /**
     * Call a static method.
     *
     * @param  string $method
     * @param  array  $args
     * @return string
     */
    protected function callStaticMethod($method, $args)
    {
        $method = str_replace('@', '::', $method);

        return call_user_func_array($method, $args);
    }

    /**
     * Call a global PHP function.
     *
     * @param  string $function
     * @param  array  $args
     * @return string
     */
    protected function callGlobalFunction($function, $args)
    {
        return call_user_func_array($function, $args);
    }

    /**
     * Call a specific class's method.
     *
     * @param  string $method
     * @param  array  $args
     * @return string
     */
    protected function callClassMethod($method, $args)
    {
        return call_user_func_array([$this, $method], $args);
    }

    /**
     * Determine whether the method is a static call.
     *
     * @param  string $method
     * @return boolean
     */
    protected function isStaticClassCall($method)
    {
        return strpos($method, '@') !== false;
    }

    /**
     * Determine whether the method is valid static method.
     *
     * @param  string  $method
     * @return boolean
     */
    protected function isValidStaticMethod($method)
    {
        $method = str_replace('@', '::', $method);

        try {
            $function = new ReflectionMethod($method);

            return $function->isStatic();

        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * Determine whether method exists on this instance.
     *
     * @param  string  $method
     * @return boolean
     */
    protected function hasClassMethod($method)
    {
        return method_exists($this, $method);
    }

    /**
     * Determine whether the function is an internal or global function.
     *
     * @param  string  $function
     * @return boolean
     */
    protected function isGlobalFunction($function)
    {
        return function_exists($function);
    }

    /**
     * Get the format type.
     *
     * @param  string $key
     * @return string
     */
    protected function getFormatForAttribute($key)
    {
        return $this->getFormats()[$key];
    }

    /**
     * Get the array of attribute formats.
     *
     * @return array
     */
    public function getFormats()
    {
        return (isset($this->formats)) ? $this->formats : [];
    }

    /**
     * Set array of attribute mappings on the model.
     *
     * @codeCoverageIgnore
     *
     * @param  array $mappings
     * @return void
     */
    public function setFormats(array $formats)
    {
        $this->formats = $formats;
    }
}
