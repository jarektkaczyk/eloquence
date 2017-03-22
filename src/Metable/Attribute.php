<?php

namespace Sofa\Eloquence\Metable;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Contracts\Attribute as AttributeContract;
use Sofa\Eloquence\Mutator\Mutator;

/**
 * @property array $attributes
 */
class Attribute extends Model implements AttributeContract
{
    /**
     * Attribute mutator instance.
     *
     * @var \Sofa\Eloquence\Contracts\Mutator
     */
    protected static $attributeMutator;

    /**
     * Custom table name.
     *
     * @var string
     */
    protected static $customTable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'meta_attributes';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'meta_id';

    /**
     * @var array
     */
    protected $getterMutators = [
        'array'      => 'json_decode',
        'StdClass'   => 'json_decode',
        'DateTime'   => 'asDateTime',
        Model::class => 'unserialize',
    ];

    /**
     * @var array
     */
    protected $setterMutators = [
        'array'      => 'json_encode',
        'StdClass'   => 'json_encode',
        'DateTime'   => 'fromDateTime',
        Model::class => 'serialize',
    ];

    /**
     * The attributes included in the model's JSON and array form.
     *
     * @var array
     */
    protected $visible = ['meta_key', 'meta_value', 'meta_type'];

    /**
     * Create new attribute instance.
     *
     * @param string|array  $key
     * @param mixed  $value
     */
    public function __construct($key = null, $value = null)
    {
        // default behaviour
        if (is_array($key)) {
            parent::__construct($key);
        } else {
            parent::__construct();

            if (is_string($key)) {
                $this->set($key, $value);
            }
        }
    }

    /**
     * Boot this model.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        if (!isset(static::$attributeMutator)) {
            if (function_exists('app') && app()->bound('eloquence.mutator')) {
                static::$attributeMutator = app('eloquence.mutator');
            } else {
                static::$attributeMutator = new Mutator;
            }
        }
    }

    /**
     * Set the meta attribute.
     *
     * @param string $key
     * @param mixed  $value
     */
    protected function set($key, $value)
    {
        $this->setMetaKey($key);
        $this->setValue($value);
    }

    /**
     * Create new AttributeBag.
     *
     * @param  array  $models
     * @return \Sofa\Eloquence\Metable\AttributeBag
     */
    public function newBag(array $models = [])
    {
        return new AttributeBag($models);
    }

    /**
     * Get the meta attribute value.
     *
     * @return mixed
     */
    public function getValue()
    {
        if ($this->hasMutator($this->attributes['meta_value'], 'getter', $this->attributes['meta_type'])) {
            return $this->mutateValue($this->attributes['meta_value'], 'getter');
        }

        return $this->castValue();
    }

    /**
     * Get the meta attribute key.
     *
     * @return string
     */
    public function getMetaKey()
    {
        return $this->attributes['meta_key'];
    }

    /**
     * Cast value to proper type.
     *
     * @return mixed
     */
    protected function castValue()
    {
        $value = $this->attributes['meta_value'];

        $validTypes = ['boolean', 'integer', 'float', 'double', 'array', 'object', 'null'];

        if (in_array($this->attributes['meta_type'], $validTypes)) {
            settype($value, $this->attributes['meta_type']);
        }

        return $value;
    }

    /**
     * Set key of the meta attribute.
     *
     * @param string $key
     *
     * @throws \InvalidArgumentException
     */
    protected function setMetaKey($key)
    {
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $key)) {
            throw new InvalidArgumentException("Provided key [{$key}] is not valid variable name.");
        }

        $this->attributes['meta_key'] = $key;
    }

    /**
     * Set type of the meta attribute.
     *
     * @param mixed $value
     */
    protected function setType($value)
    {
        $this->attributes['meta_type'] = $this->hasMutator($value, 'setter')
            ? $this->getMutatedType($value, 'setter')
            : $this->getValueType($value);
    }

    /**
     * Set value of the meta attribute.
     *
     * @param mixed $value
     *
     * @throws \Sofa\Eloquence\Metable\InvalidTypeException
     */
    public function setValue($value)
    {
        $this->setType($value);

        if ($this->hasMutator($value, 'setter')) {
            $value = $this->mutateValue($value, 'setter');
        } elseif (!$this->isStringable($value) && !is_null($value)) {
            throw new InvalidTypeException(
                "Unsupported meta value type [{$this->getValueType($value)}]."
            );
        }

        $this->attributes['meta_value'] = $value;
    }

    /**
     * Mutate attribute value.
     *
     * @param  mixed  $value
     * @param  string $dir
     * @return mixed
     */
    protected function mutateValue($value, $dir = 'setter')
    {
        $mutator = $this->getMutator($value, $dir, $this->attributes['meta_type']);

        if (method_exists($this, $mutator)) {
            return $this->{$mutator}($value);
        }

        return static::$attributeMutator->mutate($value, $mutator);
    }

    /**
     * Determine whether the value type can be set to string.
     *
     * @param  mixed   $value
     * @return boolean
     */
    protected function isStringable($value)
    {
        return is_scalar($value);
    }

    /**
     * Get the value type.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function getValueType($value)
    {
        $type = is_object($value) ? get_class($value) : gettype($value);

        // use float instead of deprecated double
        return ($type == 'double') ? 'float' : $type;
    }

    /**
     * Get the mutated type.
     *
     * @param  mixed  $value
     * @param  string $dir
     * @return string
     */
    protected function getMutatedType($value, $dir = 'setter')
    {
        foreach ($this->{"{$dir}Mutators"} as $mutated => $mutator) {
            if ($this->getValueType($value) == $mutated || $value instanceof $mutated) {
                return $mutated;
            }
        }
    }

    /**
     * Determine whether a mutator exists for the value type.
     *
     * @param  mixed   $value
     * @param  string  $dir
     * @return boolean
     */
    protected function hasMutator($value, $dir = 'setter', $type = null)
    {
        return (bool) $this->getMutator($value, $dir, $type);
    }

    /**
     * Get mutator for the type.
     *
     * @param  mixed  $value
     * @param  string $dir
     * @return string
     */
    protected function getMutator($value, $dir = 'setter', $type = null)
    {
        $type = $type ?: $this->getValueType($value);

        foreach ($this->{"{$dir}Mutators"} as $mutated => $mutator) {
            if ($type == $mutated || $value instanceof $mutated) {
                return $mutator;
            }
        }
    }

    /**
     * Allow custom table name for meta attributes via config.
     *
     * @return string
     */
    public function getTable()
    {
        return isset(static::$customTable) ? static::$customTable : parent::getTable();
    }

    /**
     * Set custom table for the meta attributes. Allows doing it only once
     * in order to mimic protected behaviour, most likely in the service
     * provider, which in turn gets the table name from configuration.
     *
     * @param string $table
     */
    public static function setCustomTable($table)
    {
        if (!isset(static::$customTable)) {
            static::$customTable = $table;
        }
    }

    /**
     * Handle casting value to string.
     *
     * @return string
     */
    public function castToString()
    {
        if ($this->attributes['meta_type'] == 'array') {
            return $this->attributes['meta_value'];
        }

        $value = $this->getValue();

        if ($this->isStringable($value) || is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Handle dynamic casting to string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->castToString();
    }
}
