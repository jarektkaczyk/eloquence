<?php namespace Sofa\Eloquence;

/**
 * @property array $formats
 */
trait Formattable
{
    /**
     * @codeCoverageIgnore
     *
     * @inheritdoc
     */
    public function setAttribute($key, $value)
    {
        if ($this->hasFormating($key)) {
            return $this->formatAttribute($key, $value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Determine whether a formating exists for an attribute.
     *
     * @param string $key
     * @return boolean
     */
    public function hasFormating($key)
    {
        $formats = $this->getFormats();

        return array_key_exists($key, $formats);
    }

    /**
     * Format the attribute.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    protected function formatAttribute($key, $value)
    {
        $format = $this->getFormatForAttribute($key);

        if ($this->hasMultipleMethod($format)) {
            return $this->callMultipleMethods($value, $format);
        }

        return $this->callSingleMethod($value, $format);
    }

    /**
     * Determine if the format has multiple methods to call.
     *
     * @param string $format
     * @return boolean
     */
    protected function hasMultipleMethod($format)
    {
        if (is_array($format) || preg_match('/[|]/', $format)) {
            return true;
        }

        return false;
    }

    /**
     * Call all methods specified.
     *
     * @param string       $value
     * @param array|string $methods
     * @return string
     */
    protected function callMultipleMethods($value, $methods)
    {
        $methods = $this->transformToArray($methods);

        foreach ($methods as $method) {
            $value = $this->callSingleMethod($value, $method);
        }

        return $value;
    }

    /**
     * Call method specified.
     *
     * @param string $value
     * @param string $method
     * @return string
     */
    protected function callSingleMethod($value, $method)
    {
        if ($this->isStaticClassCall($method) && $this->hasStaticMethod($method)) {
            return $this->callStaticMethod($method, $value);
        }

        if ($this->hasClassMethod($method)) {
            return $this->callClassMethod($method, $value);
        }

        if ($this->hasGlobalFunction($method)) {
            return $this->callGlobalFunction($method, $value);
        }

        throw new \Exception("Impossible to format the value {$value} - {$format} not found");
    }

    /**
     * Transform a string with a pipe to an array.
     *
     * @param string $format
     * @return array
     */
    protected function transformToArray($format)
    {
        return (is_array($format)) ? $format : explode('|', $format);
    }

    /**
     * Call a static method.
     *
     * @param string $method
     * @param string $value
     * @return string
     */
    protected function callStaticMethod($method, $value)
    {
        list($class, $action) = $this->parseStaticCall($method);

        return $class::$action($value);
    }

    /**
     * Call a global PHP function.
     *
     * @param string $function
     * @param string $value
     * @return string
     */
    protected function callGlobalFunction($function, $value)
    {
        return $function($value);
    }

    /**
     * Call a specific class's method.
     *
     * @param string $method
     * @param string $value
     * @return string
     */
    protected function callClassMethod($method, $value)
    {
        return $this->{$method}($value);
    }

    /**
     * Determine whether the method is a static call.
     *
     * @param string $method
     * @return boolean
     */
    protected function isStaticClassCall($method)
    {
        return (boolean) preg_match('/([A-Za-z]+)[@]([A-Za-z]+)/', $method);
    }

    /**
     * Determine whether the method is a class static method.
     *
     * @param string $method
     * @return boolean
     */
    protected function hasStaticMethod($method)
    {
        list($class, $action) = $this->parseStaticCall($method);

        return method_exists($class, $action);
    }

    /**
     * Determine whether the method is a class method.
     *
     * @param string $method
     * @return boolean
     */
    protected function hasClassMethod($method)
    {
        return method_exists($this, $method);
    }

    /**
     * Determine whether the method is a PHP global function.
     *
     * @param string $method
     * @return boolean
     */
    protected function hasGlobalFunction($method)
    {
        return function_exists($method);
    }

    /**
     * Parse method's name.
     *
     * @param string $method
     * @return string
     */
    protected function parseStaticCall($method)
    {
        return explode('@', $method);
    }

    /**
     * Get the format type.
     *
     * @param string $key
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
}
